<?php

namespace App\Services;

use App\Models\LoginAttempt;
use App\Models\SecurityEvent;
use App\Models\TentativeBloquee;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * F100 : fil des derniers événements de sécurité pour l'espace agent.
 *
 * Réunit trois journaux existants en phrases claires, sans donnée sensible en clair :
 * connexions suspectes (F37), formulaires bloqués par l'anti-robots (F81) et activité inhabituelle (F85).
 * Les e-mails et adresses IP sont toujours masqués ; la description brute des événements F85
 * (qui peut contenir un nom ou un e-mail saisi) n'est jamais affichée ici : elle reste dans /admin.
 *
 * @phpstan-type Evenement array{cle: string, source: string, id: int, date: CarbonInterface, titre: string, phrase: string, gravite: string, compte: string|null, ip: string, appareil: string|null, conseil: string}
 */
class FilSecurite
{
    public const SOURCE_CONNEXION = 'connexion';

    public const SOURCE_FORMULAIRE = 'formulaire';

    public const SOURCE_ANOMALIE = 'anomalie';

    /** @var array<string, string> */
    public const SOURCE_OPTIONS = [
        self::SOURCE_CONNEXION => 'Connexions suspectes',
        self::SOURCE_FORMULAIRE => 'Robots bloqués',
        self::SOURCE_ANOMALIE => 'Activité inhabituelle',
    ];

    /** @var array<string, string> */
    public const GRAVITE_OPTIONS = SecurityEvent::NIVEAU_OPTIONS;

    /** Gravité → état du badge (x-tn.status-badge). */
    public const GRAVITE_ETATS = [
        SecurityEvent::NIVEAU_INFO => 'info',
        SecurityEvent::NIVEAU_MOYEN => 'perturbe',
        SecurityEvent::NIVEAU_ELEVE => 'alerte',
    ];

    /** Fenêtre affichée (jours). */
    public const JOURS = 30;

    /** Nombre maximum de lignes lues par journal (le fil reste léger sur le serveur mutualisé). */
    public const LIMITE_PAR_SOURCE = 200;

    /** Sans consultation enregistrée, les événements des 7 derniers jours sont comptés comme non lus. */
    public const JOURS_NON_LUS_PAR_DEFAUT = 7;

    /**
     * Derniers événements, du plus récent au plus ancien.
     *
     * @return Collection<int, Evenement>
     */
    public function evenements(string $source = '', string $gravite = ''): Collection
    {
        $depuis = now()->subDays(self::JOURS);

        $evenements = collect();

        if ($source === '' || $source === self::SOURCE_CONNEXION) {
            $evenements = $evenements->merge($this->requeteConnexions($depuis)->latest('id')->limit(self::LIMITE_PAR_SOURCE)->get()->map($this->depuisConnexion(...)));
        }

        if ($source === '' || $source === self::SOURCE_FORMULAIRE) {
            $evenements = $evenements->merge(TentativeBloquee::query()->where('created_at', '>=', $depuis)->latest('id')->limit(self::LIMITE_PAR_SOURCE)->get()->map($this->depuisFormulaire(...)));
        }

        if ($source === '' || $source === self::SOURCE_ANOMALIE) {
            $evenements = $evenements->merge(SecurityEvent::query()->with('user:id,email')->where('created_at', '>=', $depuis)->latest('id')->limit(self::LIMITE_PAR_SOURCE)->get()->map($this->depuisAnomalie(...)));
        }

        return $evenements
            ->when(array_key_exists($gravite, self::GRAVITE_OPTIONS), fn (Collection $c) => $c->where('gravite', $gravite))
            ->sortByDesc(fn (array $e): int => $e['date']->getTimestamp())
            ->values();
    }

    /**
     * Un événement précis du fil (null s'il n'existe pas ou ne fait pas partie du fil).
     *
     * @return Evenement|null
     */
    public function trouver(string $source, int $id): ?array
    {
        return match ($source) {
            self::SOURCE_CONNEXION => ($l = $this->requeteConnexions(now()->subDays(self::JOURS))->whereKey($id)->first()) ? $this->depuisConnexion($l) : null,
            self::SOURCE_FORMULAIRE => ($t = TentativeBloquee::query()->whereKey($id)->first()) ? $this->depuisFormulaire($t) : null,
            self::SOURCE_ANOMALIE => ($s = SecurityEvent::query()->with('user:id,email')->whereKey($id)->first()) ? $this->depuisAnomalie($s) : null,
            default => null,
        };
    }

    /**
     * Nombre d'événements apparus depuis la dernière fois que l'agent a tout marqué comme lu.
     */
    public function nonLus(User $user): int
    {
        $depuis = $this->derniereLecture($user) ?? now()->subDays(self::JOURS_NON_LUS_PAR_DEFAUT);

        return $this->requeteConnexions($depuis)->where('created_at', '>', $depuis)->count()
            + TentativeBloquee::query()->where('created_at', '>', $depuis)->count()
            + SecurityEvent::query()->where('created_at', '>', $depuis)->count();
    }

    public function estNonLu(User $user, CarbonInterface $date): bool
    {
        $depuis = $this->derniereLecture($user) ?? now()->subDays(self::JOURS_NON_LUS_PAR_DEFAUT);

        return $date->greaterThan($depuis);
    }

    public function derniereLecture(User $user): ?CarbonInterface
    {
        $valeur = $user->getAttribute('securite_lue_le');

        return $valeur === null ? null : Carbon::parse($valeur);
    }

    /**
     * Mise à jour directe en base : pas d'événement de modèle (ni audit, ni compteur F85) pour un simple « lu ».
     */
    public function marquerLus(User $user): void
    {
        $maintenant = now();

        User::query()->whereKey($user->id)->toBase()->update(['securite_lue_le' => $maintenant]);
        $user->setRawAttributes(array_merge($user->getAttributes(), ['securite_lue_le' => $maintenant->toDateTimeString()]), true);
    }

    /**
     * Connexions retenues : blocages, comptes désactivés et essais sur des comptes inexistants
     * (une simple faute de frappe d'un habitant n'est pas un événement de sécurité ; elle reste dans F37).
     *
     * @return Builder<LoginAttempt>
     */
    private function requeteConnexions(CarbonInterface $depuis): Builder
    {
        return LoginAttempt::query()
            ->failed()
            ->since($depuis)
            ->where(fn ($q) => $q
                ->whereIn('reason', [LoginAttempt::REASON_LOCKED_OUT, LoginAttempt::REASON_DEACTIVATED])
                ->orWhereNull('user_id'));
    }

    /**
     * @return Evenement
     */
    private function depuisConnexion(LoginAttempt $tentative): array
    {
        $compte = self::masquerEmail($tentative->email);
        $ip = self::masquerIp($tentative->ip);

        [$titre, $phrase, $gravite, $conseil] = match (true) {
            $tentative->reason === LoginAttempt::REASON_LOCKED_OUT => [
                'Connexion bloquée',
                'Connexion bloquée après trop d’essais sur le compte '.$compte.' depuis l’adresse '.$ip.'.',
                SecurityEvent::NIVEAU_ELEVE,
                'Le titulaire a été prévenu par e-mail. Si l’habitant vous contacte, vérifiez son identité avant toute aide ; un administrateur peut lever le blocage.',
            ],
            $tentative->reason === LoginAttempt::REASON_DEACTIVATED => [
                'Compte désactivé',
                'Tentative de connexion sur le compte désactivé '.$compte.' depuis l’adresse '.$ip.'.',
                SecurityEvent::NIVEAU_MOYEN,
                'Le compte est désactivé : la connexion a été refusée. Réactivez-le seulement après vérification, depuis « Comptes citoyens ».',
            ],
            default => [
                'Compte inexistant',
                'Tentative de connexion sur un compte qui n’existe pas ('.$compte.') depuis l’adresse '.$ip.'.',
                SecurityEvent::NIVEAU_MOYEN,
                'Souvent un robot qui essaie des adresses au hasard. Si la même adresse revient beaucoup, consultez « Sécurité des connexions ».',
            ],
        };

        return $this->evenement(self::SOURCE_CONNEXION, $tentative->id, $tentative->created_at, $titre, $phrase, $gravite, $compte, $ip, $tentative->user_agent, $conseil);
    }

    /**
     * @return Evenement
     */
    private function depuisFormulaire(TentativeBloquee $tentative): array
    {
        $formulaire = TentativeBloquee::FORMULAIRE_OPTIONS[$tentative->formulaire] ?? $tentative->formulaire;
        $motif = TentativeBloquee::MOTIF_OPTIONS[$tentative->motif] ?? $tentative->motif;
        $ip = self::masquerIp($tentative->ip);

        $gravite = match ($tentative->motif) {
            TentativeBloquee::MOTIF_DEBIT => SecurityEvent::NIVEAU_ELEVE,
            TentativeBloquee::MOTIF_TROP_RAPIDE => SecurityEvent::NIVEAU_INFO,
            default => SecurityEvent::NIVEAU_MOYEN,
        };

        return $this->evenement(
            self::SOURCE_FORMULAIRE,
            $tentative->id,
            $tentative->created_at,
            'Robot bloqué',
            'Envoi du formulaire « '.$formulaire.' » bloqué ('.Str::lower($motif).') depuis l’adresse '.$ip.'.',
            $gravite,
            null,
            $ip,
            $tentative->user_agent,
            'Aucune action nécessaire : l’envoi a été refusé automatiquement et aucune donnée saisie n’a été conservée.',
        );
    }

    /**
     * @return Evenement
     */
    private function depuisAnomalie(SecurityEvent $event): array
    {
        $compte = $event->user ? self::masquerEmail($event->user->email) : null;
        $sujet = $compte !== null ? 'le compte '.$compte : 'un compte';

        [$phrase, $conseil] = match ($event->type) {
            SecurityEvent::TYPE_NOUVEL_APPAREIL => ['Connexion de '.$sujet.' depuis un appareil ou une adresse jamais vus.', 'Le titulaire a été prévenu. Rien à faire sauf s’il signale ne pas être à l’origine de cette connexion.'],
            SecurityEvent::TYPE_RAFALE => ['Rafale de requêtes inhabituelle depuis '.$sujet.'.', 'Possible usage automatisé. Prévenez un administrateur si cela se répète.'],
            SecurityEvent::TYPE_ACCES_REFUSES => ['Accès refusés répétés pour '.$sujet.' (tentative d’accès à des données d’autrui ?).', 'Les accès ont été refusés. Prévenez un administrateur si le compte insiste.'],
            SecurityEvent::TYPE_MODIFICATIONS_MASSIVES => ['Nombreuses modifications en peu de temps depuis '.$sujet.'.', 'À vérifier rapidement : prévenez un administrateur, qui peut verrouiller le compte.'],
            SecurityEvent::TYPE_CONNEXION_BLOQUEE => ['Connexion bloquée après plusieurs mots de passe incorrects sur '.$sujet.'.', 'Le blocage est temporaire. Vérifiez l’identité de l’habitant s’il vous contacte.'],
            SecurityEvent::TYPE_COMPTE_VERROUILLE => [Str::ucfirst($sujet).' a été verrouillé temporairement.', 'Le verrou se lève tout seul à l’échéance.'],
            SecurityEvent::TYPE_COMPTE_DEVERROUILLE => [Str::ucfirst($sujet).' a été déverrouillé.', 'Information : aucune action nécessaire.'],
            SecurityEvent::TYPE_SESSIONS_FERMEES => ['Toutes les sessions de '.$sujet.' ont été fermées.', 'Information : l’habitant devra se reconnecter.'],
            default => ['Événement de sécurité sur '.$sujet.'.', 'Prévenez un administrateur en cas de doute.'],
        };

        $gravite = array_key_exists($event->niveau, self::GRAVITE_OPTIONS) ? $event->niveau : SecurityEvent::NIVEAU_INFO;

        return $this->evenement(self::SOURCE_ANOMALIE, $event->id, $event->created_at ?? now(), $event->libelleType(), $phrase, $gravite, $compte, self::masquerIp($event->ip), $event->user_agent, $conseil);
    }

    /**
     * @return Evenement
     */
    private function evenement(string $source, int $id, ?CarbonInterface $date, string $titre, string $phrase, string $gravite, ?string $compte, string $ip, ?string $userAgent, string $conseil): array
    {
        return [
            'cle' => $source.'-'.$id,
            'source' => $source,
            'id' => $id,
            'date' => $date ?? now(),
            'titre' => $titre,
            'phrase' => $phrase,
            'gravite' => $gravite,
            'compte' => $compte,
            'ip' => $ip,
            'appareil' => self::appareil($userAgent),
            'conseil' => $conseil,
        ];
    }

    /**
     * « awa.rakoto@example.com » → « aw•••@example.com ».
     */
    public static function masquerEmail(?string $email): string
    {
        if ($email === null || $email === '') {
            return 'inconnu';
        }

        [$nom, $domaine] = array_pad(explode('@', $email, 2), 2, null);

        return Str::substr($nom, 0, 2).'•••'.($domaine !== null ? '@'.$domaine : '');
    }

    /**
     * IPv4 : « 41.188.37.204 » → « 41.188.x.x » ; IPv6 : deux premiers groupes seulement.
     */
    public static function masquerIp(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return 'inconnue';
        }

        if (preg_match('/^(\d{1,3})\.(\d{1,3})\./', $ip, $m) === 1) {
            return $m[1].'.'.$m[2].'.x.x';
        }

        $groupes = explode(':', $ip);

        return count($groupes) > 2 ? $groupes[0].':'.$groupes[1].':…' : 'masquée';
    }

    /**
     * Type d'appareil lisible à partir du navigateur déclaré (jamais la chaîne brute).
     */
    public static function appareil(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        $ua = Str::lower($userAgent);

        return match (true) {
            Str::contains($ua, ['python', 'curl', 'wget', 'bot', 'go-http', 'java/', 'httpclient']) => 'Script automatisé (robot)',
            Str::contains($ua, ['android', 'iphone', 'mobile']) => 'Téléphone',
            Str::contains($ua, ['ipad', 'tablet']) => 'Tablette',
            default => 'Ordinateur',
        };
    }
}
