<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Avis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Throwable;

/**
 * F69 : détection des comportements suspects.
 *
 * Chaque événement (403, CSRF invalide, lien signé altéré, upload refusé, limite de débit, motif suspect)
 * est journalisé dans le journal d'audit F47 (route, méthode, IP approximative : jamais le contenu de la requête).
 * Au-delà d'un seuil par compte ou par IP (config/security.php), l'accès est bloqué temporairement (429)
 * et les administrateurs reçoivent une alerte, au plus une par compte ou IP et par heure.
 * Un administrateur n'est jamais bloqué : il doit toujours pouvoir consulter les alertes.
 */
class SecurityMonitor
{
    public const ACCES_REFUSE = 'securite_acces_refuse';

    public const CSRF = 'securite_csrf';

    public const SIGNATURE = 'securite_signature';

    public const LIMITE = 'securite_limite';

    public const UPLOAD = 'securite_upload';

    public const MOTIF = 'securite_motif';

    public const CONNEXION = 'securite_connexion';

    public const BLOCAGE = 'securite_blocage';

    /** Motifs évidents d'attaque dans l'URL ou les paramètres (traversée de répertoire, balise script). */
    private const MOTIFS = ['../', '..\\', '..%2f', '..%5c', '<script', '%3cscript', 'javascript:'];

    private static bool $enCours = false;

    public static function signaler(string $action, ?Request $request = null): void
    {
        // Garde-fou : un événement levé pendant le traitement d'un autre n'est pas recompté.
        if (self::$enCours) {
            return;
        }

        self::$enCours = true;

        try {
            $request ??= request();
            $route = self::route($request);

            AuditLogger::logSecurite($action, $route, [
                'route' => $route,
                'methode' => $request->method(),
            ], self::ipApproximative($request));

            foreach (self::compteurs($request) as $cle => $max) {
                RateLimiter::hit('suspect:'.$cle, (int) config('security.suspect.window_seconds'));

                if (RateLimiter::attempts('suspect:'.$cle) >= $max) {
                    self::bloquer($cle, $route, $request);
                }
            }
        } catch (Throwable $e) {
            // La détection ne doit jamais casser la réponse.
            report($e);
        } finally {
            self::$enCours = false;
        }
    }

    /**
     * Vrai si le compte connecté ou l'IP de la requête est bloqué temporairement.
     */
    public static function estBloque(Request $request): bool
    {
        if ($request->user()?->isAdmin()) {
            return false;
        }

        foreach (array_keys(self::compteurs($request)) as $cle) {
            if (Cache::has('suspect:block:'.$cle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Le paramètre contient-il un motif d'attaque évident ? (l'événement est journalisé, la requête n'est pas bloquée seule)
     */
    public static function contientMotif(Request $request): bool
    {
        $valeurs = [rawurldecode($request->getRequestUri())];

        $parametres = $request->query();

        array_walk_recursive($parametres, function (mixed $valeur) use (&$valeurs): void {
            $valeurs[] = (string) $valeur;
        });

        foreach ($valeurs as $valeur) {
            if (Str::contains(Str::lower($valeur), self::MOTIFS)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Libellés des types d'événements (filtres de la page admin).
     *
     * @return array<string, string>
     */
    public static function types(): array
    {
        return array_filter(AuditLog::ACTION_LABELS, fn (string $cle): bool => str_starts_with($cle, 'securite_'), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Compteurs concernés et leur seuil : le compte connecté (seuil serré) et l'IP (seuil large, IP partagées).
     *
     * @return array<string, int>
     */
    private static function compteurs(Request $request): array
    {
        $compteurs = [];

        if ($request->user() !== null) {
            $compteurs['user:'.$request->user()->getAuthIdentifier()] = (int) config('security.suspect.user_max');
        }

        $compteurs['ip:'.$request->ip()] = (int) config('security.suspect.ip_max');

        return $compteurs;
    }

    private static function bloquer(string $cle, string $route, Request $request): void
    {
        $minutes = (int) config('security.suspect.block_minutes');

        if (! Cache::add('suspect:block:'.$cle, true, now()->addMinutes($minutes))) {
            return;
        }

        AuditLogger::logSecurite(self::BLOCAGE, $route, [
            'route' => $route,
            'compteur' => RateLimiter::attempts('suspect:'.$cle),
        ], self::ipApproximative($request));

        if (! Cache::add('suspect:alerte:'.$cle, true, now()->addMinutes((int) config('security.suspect.notify_every_minutes')))) {
            return;
        }

        $qui = $request->user() instanceof User ? 'le compte « '.$request->user()->name.' »' : 'un visiteur non connecté';
        $admins = User::query()->where('role_id', Role::idFor(Role::ADMIN))->get();

        try {
            Notification::send($admins, new Avis(
                'Alerte sécurité : activité suspecte bloquée',
                [
                    'Plusieurs actions refusées ont été détectées pour '.$qui.' (IP '.self::ipApproximative($request).').',
                    'L’accès est bloqué pendant '.$minutes.' minutes. Détail dans l’espace admin, « Alertes sécurité ».',
                ],
                'Voir les alertes',
                url('/admin/alertes-securite'),
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static function route(Request $request): string
    {
        // Action Livewire : on journalise la page d'origine plutôt que l'URL technique /livewire/update.
        if (Livewire::isLivewireRequest()) {
            return Str::limit('/'.ltrim((string) Livewire::originalPath(), '/'), 120, '');
        }

        return $request->route()?->getName() ?? Str::limit('/'.ltrim($request->path(), '/'), 120, '');
    }

    private static function ipApproximative(Request $request): ?string
    {
        return app(DeviceRecognizer::class)->maskIp($request->ip());
    }
}
