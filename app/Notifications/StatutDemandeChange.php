<?php

namespace App\Notifications;

use App\Models\Demarche;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * F49 : prévient le propriétaire d'une démarche ou d'un signalement que son état a changé (cloche + e-mail).
 * Étend Avis : mêmes clés sujet / lignes / libelle / url, plus de quoi retrouver la demande (filtrage du suivi, ouverture).
 * Aucune donnée sensible n'est stockée (ni description, ni nom d'agent).
 */
class StatutDemandeChange extends Avis
{
    public const TYPE_DEMARCHE = 'demarche';

    public const TYPE_SIGNALEMENT = 'signalement';

    /** Types acceptés pour reconstruire un lien (liste blanche, jamais l'URL stockée). */
    public const TYPES = [self::TYPE_DEMARCHE, self::TYPE_SIGNALEMENT];

    /** « Ce que vous devez faire » selon le type de demande et son nouvel état (textes traduits à l'affichage). */
    public const QUOI_FAIRE = [
        self::TYPE_DEMARCHE => [
            'deposee' => 'Rien à faire pour l’instant : votre demande attend un agent.',
            'en_cours' => 'Rien à faire pour l’instant : un agent étudie votre demande.',
            'traitee' => 'Consultez le résultat dans votre suivi.',
            'refusee' => 'Consultez le motif dans votre suivi. Vous pouvez déposer une nouvelle demande ou écrire à la mairie.',
        ],
        self::TYPE_SIGNALEMENT => [
            'nouveau' => 'Rien à faire pour l’instant : votre signalement attend un agent.',
            'en_cours' => 'Une intervention est prévue, rien à faire.',
            'resolu' => 'Si le problème revient, faites un nouveau signalement.',
            'rejete' => 'Consultez la raison dans votre suivi. Écrivez-nous si vous n’êtes pas d’accord.',
        ],
    ];

    public const QUOI_FAIRE_DEFAUT = 'Consultez votre suivi pour en savoir plus.';

    /** États où le citoyen peut vouloir écrire à la mairie (lien vers la messagerie D04). */
    public const ETATS_CONTACT = ['refusee', 'rejete'];

    public string $demandeType;

    public int $demandeId;

    public string $demandeLibelle;

    public function __construct(Demarche|Signalement $demande, public string $statutAvant, public string $statutApres)
    {
        $this->demandeType = $demande instanceof Demarche ? self::TYPE_DEMARCHE : self::TYPE_SIGNALEMENT;
        $this->demandeId = (int) $demande->getKey();
        $this->demandeLibelle = $demande instanceof Demarche
            ? (string) $demande->titre
            : Signalement::libelleCategorie((string) $demande->categorie).' — '.Str::limit((string) $demande->lieu, 40);

        $donnees = $this->donnees();

        parent::__construct(
            self::sujetDepuis($donnees),
            self::lignesDepuis($donnees),
            __('Voir ma demande'),
            self::urlDepuis($donnees),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return parent::toArray($notifiable) + $this->donnees();
    }

    /**
     * @return array{demande_type: string, demande_id: int, demande_libelle: string, statut_avant: string, statut_apres: string}
     */
    private function donnees(): array
    {
        return [
            'demande_type' => $this->demandeType,
            'demande_id' => $this->demandeId,
            'demande_libelle' => $this->demandeLibelle,
            'statut_avant' => $this->statutAvant,
            'statut_apres' => $this->statutApres,
        ];
    }

    /**
     * Vrai si les données stockées sont celles d'un avis F49 exploitable.
     *
     * @param  array<string, mixed>  $data
     */
    public static function estAvisDeStatut(array $data): bool
    {
        return in_array($data['demande_type'] ?? null, self::TYPES, true)
            && is_numeric($data['demande_id'] ?? null);
    }

    public static function libelleStatut(string $type, string $statut): string
    {
        return $type === self::TYPE_DEMARCHE ? Demarche::libelleStatut($statut) : Signalement::libelleStatut($statut);
    }

    public static function quoiFaire(string $type, string $statut): string
    {
        return __(self::QUOI_FAIRE[$type][$statut] ?? self::QUOI_FAIRE_DEFAUT);
    }

    /**
     * Sujet dans la langue courante : « Votre demande « Acte de naissance » est maintenant : En cours ».
     *
     * @param  array<string, mixed>  $data
     */
    public static function sujetDepuis(array $data): string
    {
        $type = (string) ($data['demande_type'] ?? '');
        $parametres = [
            'demande' => (string) ($data['demande_libelle'] ?? ''),
            'etat' => self::libelleStatut($type, (string) ($data['statut_apres'] ?? '')),
        ];

        return $type === self::TYPE_DEMARCHE
            ? __('Votre demande « :demande » est maintenant : :etat', $parametres)
            : __('Votre signalement :demande est maintenant : :etat', $parametres);
    }

    /**
     * Lignes dans la langue courante : état précédent, nouvel état, puis ce qu'il faut faire.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public static function lignesDepuis(array $data): array
    {
        $type = (string) ($data['demande_type'] ?? '');
        $apres = (string) ($data['statut_apres'] ?? '');

        return [
            __('État précédent : :etat', ['etat' => self::libelleStatut($type, (string) ($data['statut_avant'] ?? ''))]),
            __('Nouvel état : :etat', ['etat' => self::libelleStatut($type, $apres)]),
            self::ligneQuoiFaire($type, $apres),
        ];
    }

    public static function ligneQuoiFaire(string $type, string $statut): string
    {
        return __('Ce que vous devez faire : :action', ['action' => self::quoiFaire($type, $statut)]);
    }

    /**
     * Lien vers la demande reconstruit depuis le type (liste blanche) et l'identifiant : jamais l'URL stockée.
     *
     * @param  array<string, mixed>  $data
     */
    public static function urlDepuis(array $data): ?string
    {
        if (! self::estAvisDeStatut($data)) {
            return null;
        }

        return $data['demande_type'] === self::TYPE_DEMARCHE
            ? route('demarches.show', (int) $data['demande_id'])
            : route('signalements.show', (int) $data['demande_id']);
    }

    /**
     * Avis de changement d'état d'une demande, limités aux notifications de $user (jamais celles d'un autre),
     * du plus récent au plus ancien. Requête JSON compatible SQLite (tests) et MariaDB (production).
     *
     * @return Collection<int, DatabaseNotification>
     */
    public static function notificationsDe(User $user, Demarche|Signalement $demande): Collection
    {
        return $user->notifications()
            ->where('type', self::class)
            ->where('data->demande_type', $demande instanceof Demarche ? self::TYPE_DEMARCHE : self::TYPE_SIGNALEMENT)
            ->where('data->demande_id', (int) $demande->getKey())
            ->get();
    }
}
