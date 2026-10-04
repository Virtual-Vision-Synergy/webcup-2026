<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Demarche;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * F88 : export personnalisé des demandes des habitants (même requête que la liste agent, scope F70 visibleTo).
 *
 * Tout ce qui vient du navigateur (filtres, colonnes, format) passe par une LISTE BLANCHE : une valeur inconnue
 * est ignorée, jamais injectée dans la requête ni utilisée comme nom de colonne.
 * Données personnelles du demandeur (nom, e-mail, téléphone, quartier, situation : champs confidentiels F70)
 * volontairement NON proposées ; seul un identifiant anonymisé (HMAC stable, non réversible) peut être ajouté.
 */
class ExportDemarches
{
    /** @var array<string, string> Colonnes exportables : clé (JSON) => en-tête (CSV, aperçu). */
    public const COLONNES = [
        'reference' => 'Référence',
        'titre' => 'Objet de la demande',
        'created_at' => 'Date de création',
        'service' => 'Service',
        'categorie' => 'Catégorie',
        'statut' => 'Statut',
        'priorite' => 'Priorité',
        'urgence_medicale' => 'Urgence médicale',
        'pris_en_charge_le' => 'Date de prise en charge',
        'agent' => 'Agent assigné',
        'cloture_le' => 'Date de clôture',
        'updated_at' => 'Dernière mise à jour',
        'demandeur_anonyme' => 'Demandeur (identifiant anonymisé)',
    ];

    /** @var list<string> */
    public const COLONNES_PAR_DEFAUT = ['reference', 'created_at', 'service', 'categorie', 'statut', 'priorite', 'cloture_le'];

    /** @var array<string, string> */
    public const FORMATS = ['csv' => 'CSV (Excel)', 'json' => 'JSON'];

    /** @var array<string, string> Périodes relatives, recalculées à chaque export (préréglages). '' = dates choisies. */
    public const PERIODES = [
        '' => 'Dates choisies',
        '7j' => '7 derniers jours',
        '30j' => '30 derniers jours',
        'mois' => 'Mois en cours',
        'mois_dernier' => 'Mois dernier',
    ];

    /** États terminés : la date de clôture est la dernière mise à jour du dossier. */
    public const STATUTS_CLOS = ['traitee', 'refusee'];

    /** Nombre de lignes montrées par l'aperçu. */
    public const LIGNES_APERCU = 5;

    /**
     * Filtres nettoyés : toute valeur hors liste blanche devient ''.
     *
     * @param  array<string, mixed>  $saisie
     * @return array{periode: string, du: string, au: string, service: string, statut: string, priorite: string}
     */
    public static function nettoyerFiltres(array $saisie): array
    {
        $texte = fn (string $cle): string => is_string($saisie[$cle] ?? null) ? trim($saisie[$cle]) : '';

        $periode = $texte('periode');
        $service = $texte('service');
        $statut = $texte('statut');
        $priorite = $texte('priorite');

        return [
            'periode' => array_key_exists($periode, self::PERIODES) ? $periode : '',
            'du' => self::dateValide($texte('du')) ? $texte('du') : '',
            'au' => self::dateValide($texte('au')) ? $texte('au') : '',
            'service' => ctype_digit($service) ? $service : '',
            'statut' => in_array($statut, Demarche::STATUT_OPTIONS, true) ? $statut : '',
            'priorite' => in_array($priorite, Demarche::PRIORITE_OPTIONS, true) ? $priorite : '',
        ];
    }

    /**
     * Colonnes choisies, réduites à la liste blanche et remises dans l'ordre de COLONNES (sans doublon).
     *
     * @param  array<mixed>  $saisie
     * @return list<string>
     */
    public static function nettoyerColonnes(array $saisie): array
    {
        $choisies = array_filter($saisie, 'is_string');

        return array_values(array_filter(array_keys(self::COLONNES), fn (string $cle): bool => in_array($cle, $choisies, true)));
    }

    public static function nettoyerFormat(string $format): string
    {
        return array_key_exists($format, self::FORMATS) ? $format : 'csv';
    }

    /**
     * Bornes de la période (UTC), en jours entiers à l'heure de Madagascar. Une période relative l'emporte sur les dates.
     *
     * @param  array{periode: string, du: string, au: string}  $filtres
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    public static function bornes(array $filtres): array
    {
        $aujourdhui = Carbon::now(AuditLog::FUSEAU);

        [$du, $au] = match ($filtres['periode']) {
            '7j' => [$aujourdhui->copy()->subDays(6)->startOfDay(), $aujourdhui->copy()->endOfDay()],
            '30j' => [$aujourdhui->copy()->subDays(29)->startOfDay(), $aujourdhui->copy()->endOfDay()],
            'mois' => [$aujourdhui->copy()->startOfMonth(), $aujourdhui->copy()->endOfDay()],
            'mois_dernier' => [$aujourdhui->copy()->subMonthNoOverflow()->startOfMonth(), $aujourdhui->copy()->subMonthNoOverflow()->endOfMonth()],
            default => [
                self::dateValide($filtres['du']) ? Carbon::createFromFormat('!Y-m-d', $filtres['du'], AuditLog::FUSEAU)?->startOfDay() : null,
                self::dateValide($filtres['au']) ? Carbon::createFromFormat('!Y-m-d', $filtres['au'], AuditLog::FUSEAU)?->endOfDay() : null,
            ],
        };

        return [$du?->utc(), $au?->utc()];
    }

    /**
     * Même requête que la liste agent (F70 : un agent ne voit que les démarches de ses services, l'admin tout).
     * Pas d'orderBy : la lecture par paquets (chunkById / lazyById) trie par identifiant.
     *
     * @param  array{periode: string, du: string, au: string, service: string, statut: string, priorite: string}  $filtres
     * @return Builder<Demarche>
     */
    public static function requete(User $user, array $filtres): Builder
    {
        [$du, $au] = self::bornes($filtres);

        return Demarche::query()
            ->visibleTo($user)
            ->with(['service:id,nom,categorie', 'prisEnChargePar:id,name'])
            ->when($du, fn (Builder $q) => $q->where('created_at', '>=', $du))
            ->when($au, fn (Builder $q) => $q->where('created_at', '<=', $au))
            ->when($filtres['service'] !== '', fn (Builder $q) => $q->where('service_id', (int) $filtres['service']))
            ->when($filtres['statut'] !== '', fn (Builder $q) => $q->where('statut', $filtres['statut']))
            ->when($filtres['priorite'] !== '', fn (Builder $q) => $q->where('priorite', $filtres['priorite']));
    }

    /**
     * Services proposés dans le filtre : ceux de l'agent, tous pour l'admin.
     *
     * @return Collection<int, string>
     */
    public static function servicesPour(User $user): Collection
    {
        return Service::query()
            ->when(! $user->isAdmin(), fn (Builder $q) => $q->whereKey($user->serviceIds()))
            ->orderBy('nom')
            ->pluck('nom', 'id');
    }

    /**
     * Ligne exportée : clé de colonne => valeur. CSV : dates « JJ/MM/AAAA HH:MM », booléens « Oui / Non » ;
     * JSON : dates ISO 8601, vrais booléens.
     *
     * @param  list<string>  $colonnes
     * @return array<string, string|bool|null>
     */
    public static function ligne(Demarche $demarche, array $colonnes, bool $pourJson = false): array
    {
        $date = fn (?\DateTimeInterface $valeur): ?string => $valeur === null
            ? null
            : Carbon::instance($valeur)->setTimezone(AuditLog::FUSEAU)->format($pourJson ? 'c' : 'd/m/Y H:i');

        $ligne = [];

        foreach ($colonnes as $colonne) {
            $ligne[$colonne] = match ($colonne) {
                'reference' => $demarche->numeroSuivi(),
                'titre' => (string) $demarche->titre,
                'created_at' => $date($demarche->created_at),
                'service' => $demarche->service?->nom,
                'categorie' => Service::labelCategorie($demarche->service?->categorie),
                'statut' => Demarche::libelleStatut((string) $demarche->statut),
                'priorite' => Demarche::libellePriorite((string) $demarche->priorite),
                'urgence_medicale' => $pourJson ? (bool) $demarche->urgence_medicale : ($demarche->urgence_medicale ? 'Oui' : 'Non'),
                'pris_en_charge_le' => $date($demarche->pris_en_charge_le),
                'agent' => $demarche->prisEnChargePar?->name,
                'cloture_le' => in_array($demarche->statut, self::STATUTS_CLOS, true) ? $date($demarche->updated_at) : null,
                'updated_at' => $date($demarche->updated_at),
                'demandeur_anonyme' => self::identifiantAnonyme($demarche->user_id),
                default => null,
            };
        }

        return $ligne;
    }

    /**
     * Identifiant stable du demandeur pour regrouper ses demandes sans l'identifier : HMAC (clé de l'application),
     * impossible à inverser ou à recalculer sans la clé.
     */
    public static function identifiantAnonyme(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        return 'HAB-'.substr(hash_hmac('sha256', 'f88-demandeur:'.$userId, (string) config('app.key')), 0, 12);
    }

    /**
     * Résumé lisible des filtres actifs (journal, liste des préréglages).
     *
     * @param  array{periode: string, du: string, au: string, service: string, statut: string, priorite: string}  $filtres
     * @param  Collection<int, string>|null  $services  noms des services déjà chargés (évite une requête par préréglage)
     */
    public static function resumeFiltres(array $filtres, ?Collection $services = null): string
    {
        $morceaux = [];

        if ($filtres['periode'] !== '') {
            $morceaux[] = self::PERIODES[$filtres['periode']];
        } else {
            if ($filtres['du'] !== '') {
                $morceaux[] = 'du '.$filtres['du'];
            }
            if ($filtres['au'] !== '') {
                $morceaux[] = 'au '.$filtres['au'];
            }
        }

        if ($filtres['service'] !== '') {
            $nom = $services !== null ? $services->get((int) $filtres['service']) : Service::query()->whereKey((int) $filtres['service'])->value('nom');
            $morceaux[] = 'service '.($nom ?? '#'.$filtres['service']);
        }
        if ($filtres['statut'] !== '') {
            $morceaux[] = 'statut '.Demarche::libelleStatut($filtres['statut']);
        }
        if ($filtres['priorite'] !== '') {
            $morceaux[] = 'priorité '.Demarche::libellePriorite($filtres['priorite']);
        }

        return $morceaux === [] ? 'aucun filtre' : implode(' · ', $morceaux);
    }

    private static function dateValide(string $date): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return false;
        }

        [$annee, $mois, $jour] = array_map(intval(...), explode('-', $date));

        return checkdate($mois, $jour, $annee);
    }
}
