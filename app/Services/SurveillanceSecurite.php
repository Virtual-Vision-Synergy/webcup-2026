<?php

namespace App\Services;

use App\Models\KnownDevice;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\ActiviteInhabituelleDetectee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * F85 : seul point d'écriture des événements de sécurité.
 *
 * Signaux surveillés : nouvel appareil / nouvelle adresse (F54), rafale de requêtes, accès refusés répétés (F70),
 * modifications massives (journal F47), connexions bloquées (F37).
 *
 * Principe « aucune gêne pour l'usage normal » : un seuil franchi crée UN événement par fenêtre et prévient
 * l'utilisateur, sans rien bloquer. Seul un cumul de signaux forts sur une heure verrouille le compte
 * quelques minutes (jamais un admin). Une erreur de surveillance ne casse jamais l'action en cours.
 */
class SurveillanceSecurite
{
    /** Poids de chaque niveau dans le score de suspicion. */
    public const POIDS = [
        SecurityEvent::NIVEAU_INFO => 0,
        SecurityEvent::NIVEAU_MOYEN => 1,
        SecurityEvent::NIVEAU_ELEVE => 3,
    ];

    /**
     * @param  array<string, mixed>  $details
     */
    public function enregistrer(string $type, ?User $user, string $niveau, string $description, array $details = [], ?Request $request = null): ?SecurityEvent
    {
        try {
            $request ??= app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

            $event = new SecurityEvent;
            $event->user_id = $user?->id;
            $event->type = $type;
            $event->niveau = $niveau;
            $event->description = Str::limit($description, 250);
            $event->details = $details === [] ? null : $details;
            $event->ip = $request?->ip();
            $event->user_agent = $request ? (Str::limit((string) $request->userAgent(), 250, '') ?: null) : null;
            $event->created_at = now();
            $event->save();

            if ($user !== null && $niveau !== SecurityEvent::NIVEAU_INFO) {
                $this->verrouillerSiCumul($user);
            }

            return $event;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Connexion depuis un appareil ou une adresse jamais vus (l'utilisateur est déjà prévenu par NewDeviceLogin).
     */
    public function nouvelAppareil(User $user, KnownDevice $device): void
    {
        $this->enregistrer(
            SecurityEvent::TYPE_NOUVEL_APPAREIL,
            $user,
            SecurityEvent::NIVEAU_INFO,
            'Connexion depuis un nouvel appareil : '.$device->libelleAppareil().', adresse '.$device->ipAffichee().'.',
        );
    }

    /**
     * Compte chaque requête d'un utilisateur connecté ; au-delà du seuil par minute : rafale (une fois par minute).
     */
    public function compterRequete(User $user, Request $request): void
    {
        $seuil = (int) config('security.surveillance.rafale_requetes');

        if ($this->franchitSeuil('f85:requetes:'.$user->id, $seuil, 60)) {
            $this->signaler(
                SecurityEvent::TYPE_RAFALE,
                $user,
                SecurityEvent::NIVEAU_MOYEN,
                'Plus de '.$seuil.' requêtes en une minute depuis ce compte.',
                ['seuil' => $seuil, 'page' => '/'.ltrim($request->path(), '/')],
                $request,
            );
        }
    }

    /**
     * Accès refusé (403) : au-delà du seuil sur la fenêtre, événement « accès refusés répétés ».
     */
    public function compterRefus(User $user): void
    {
        $seuil = (int) config('security.surveillance.refus_max');
        $minutes = (int) config('security.surveillance.refus_minutes');

        if ($this->franchitSeuil('f85:refus:'.$user->id, $seuil, $minutes * 60)) {
            $this->signaler(
                SecurityEvent::TYPE_ACCES_REFUSES,
                $user,
                SecurityEvent::NIVEAU_MOYEN,
                $seuil.' accès refusés ou plus en '.$minutes.' minutes (tentative d’accès à des données d’autrui ?).',
                ['seuil' => $seuil, 'minutes' => $minutes],
            );
        }
    }

    /**
     * Création / modification / suppression : au-delà du seuil sur la fenêtre, événement « modifications massives ».
     */
    public function compterModification(User $user): void
    {
        $seuil = (int) config('security.surveillance.modifications_max');
        $minutes = (int) config('security.surveillance.modifications_minutes');

        if ($this->franchitSeuil('f85:modifications:'.$user->id, $seuil, $minutes * 60)) {
            $this->signaler(
                SecurityEvent::TYPE_MODIFICATIONS_MASSIVES,
                $user,
                SecurityEvent::NIVEAU_ELEVE,
                $seuil.' modifications ou plus en '.$minutes.' minutes depuis ce compte.',
                ['seuil' => $seuil, 'minutes' => $minutes],
            );
        }
    }

    /**
     * Connexion bloquée après trop d'échecs de mot de passe (F37 prévient déjà le titulaire par e-mail).
     */
    public function connexionBloquee(?User $user, string $email, Request $request): void
    {
        $this->enregistrer(
            SecurityEvent::TYPE_CONNEXION_BLOQUEE,
            $user,
            SecurityEvent::NIVEAU_MOYEN,
            'Connexion bloquée après plusieurs mots de passe incorrects'.($user === null ? ' (compte inexistant : '.Str::limit($email, 80).')' : '').'.',
            [],
            $request,
        );
    }

    /**
     * Verrouille temporairement un compte et ferme toutes ses sessions (action admin ou verrouillage automatique).
     */
    public function verrouiller(User $user, int $minutes, ?User $par = null, string $motif = ''): void
    {
        $user->forceFill(['verrouille_jusqu_au' => now()->addMinutes($minutes)])->save();
        app(DeviceRecognizer::class)->logoutOtherSessions($user, null);

        $this->enregistrer(
            SecurityEvent::TYPE_COMPTE_VERROUILLE,
            $user,
            SecurityEvent::NIVEAU_INFO,
            'Compte verrouillé '.$minutes.' min '.($par ? 'par '.$par->name : 'automatiquement').($motif !== '' ? ' : '.$motif : '.'),
            ['minutes' => $minutes, 'par' => $par?->id],
        );
    }

    public function deverrouiller(User $user, ?User $par = null): void
    {
        $user->forceFill(['verrouille_jusqu_au' => null])->save();

        $this->enregistrer(
            SecurityEvent::TYPE_COMPTE_DEVERROUILLE,
            $user,
            SecurityEvent::NIVEAU_INFO,
            'Compte déverrouillé'.($par ? ' par '.$par->name : '').'.',
            ['par' => $par?->id],
        );
    }

    /**
     * Ferme toutes les sessions d'un compte (action admin) : il devra se reconnecter partout.
     */
    public function fermerSessions(User $user, ?User $par = null): void
    {
        app(DeviceRecognizer::class)->logoutOtherSessions($user, null);

        $this->enregistrer(
            SecurityEvent::TYPE_SESSIONS_FERMEES,
            $user,
            SecurityEvent::NIVEAU_INFO,
            'Toutes les sessions ont été fermées'.($par ? ' par '.$par->name : '').'.',
            ['par' => $par?->id],
        );
    }

    /**
     * Score de suspicion d'un compte depuis $depuisHeures heures (info = 0, moyen = 1, élevé = 3).
     */
    public function score(User $user, int $depuisHeures = 24): int
    {
        return $user->securityEvents()
            ->where('created_at', '>=', now()->subHours($depuisHeures))
            ->pluck('niveau')
            ->sum(fn (string $niveau): int => self::POIDS[$niveau] ?? 0);
    }

    /**
     * Événement + avertissement de l'utilisateur dans la cloche (« ce n'était pas vous ? »).
     *
     * @param  array<string, mixed>  $details
     */
    private function signaler(string $type, User $user, string $niveau, string $description, array $details = [], ?Request $request = null): void
    {
        $event = $this->enregistrer($type, $user, $niveau, $description, $details, $request);

        if ($event === null) {
            return;
        }

        try {
            $user->notify(new ActiviteInhabituelleDetectee($event));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Incrémente le compteur et renvoie vrai UNE seule fois par fenêtre : quand le compteur dépasse le seuil.
     */
    private function franchitSeuil(string $cle, int $seuil, int $secondes): bool
    {
        try {
            return RateLimiter::hit($cle, $secondes) === $seuil + 1;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Cumul de signaux forts sur une heure : verrouillage automatique court (jamais un admin, jamais déjà verrouillé).
     */
    private function verrouillerSiCumul(User $user): void
    {
        if ($user->isAdmin() || $user->estVerrouille()) {
            return;
        }

        if ($this->score($user, 1) < (int) config('security.surveillance.score_verrouillage_auto')) {
            return;
        }

        $this->verrouiller(
            $user,
            (int) config('security.surveillance.verrouillage_auto_minutes'),
            null,
            'cumul de signaux d’activité inhabituelle sur une heure',
        );
    }
}
