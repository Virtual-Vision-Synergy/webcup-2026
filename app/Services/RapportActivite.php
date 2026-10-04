<?php

namespace App\Services;

use App\Models\Demarche;
use App\Models\Service;
use App\Models\Signalement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use stdClass;

/**
 * F103 : rapport synthétique de l'activité de la plateforme sur une période (demandes, délais, signalements,
 * urgences), comparé à la période précédente de même durée. Phrases et points d'attention générés par règles (sans IA).
 *
 * Période en LISTE BLANCHE : une période inconnue ou des dates invalides retombent sur les 30 derniers jours.
 * Le délai de traitement va du dépôt à la dernière mise à jour d'une demande traitée ou refusée (comme F56).
 *
 * @phpstan-type LigneService array{nom: string, demandes: int, demandes_avant: int, evolution: int|null, traitees: int, delai: float|null, delai_avant: float|null, evolution_delai: int|null}
 * @phpstan-type LigneQuartier array{nom: string, signalements: int, ouverts: int, avant: int}
 * @phpstan-type Point array{niveau: string, texte: string}
 */
class RapportActivite
{
    /** @var array<int|string, string> Périodes proposées : valeur => libellé. */
    public const PERIODES = ['7' => '7 derniers jours', '30' => '30 derniers jours', 'perso' => 'Période personnalisée'];

    /** Durée maximale d'une période personnalisée (évite une requête trop lourde). */
    public const JOURS_MAXIMUM = 366;

    /** Statuts d'une demande qui a reçu sa réponse. */
    public const STATUTS_TERMINES = ['traitee', 'refusee'];

    /** Variation (en %) à partir de laquelle un écart est signalé. */
    public const SEUIL_VARIATION = 20;

    /** Volume minimal pour qu'un pourcentage soit jugé significatif. */
    public const VOLUME_MINIMAL = 3;

    /** Taux de traitement (en %) sous lequel un point d'attention est généré. */
    public const SEUIL_TRAITEMENT = 50;

    /** Part des signalements (en %) à partir de laquelle un quartier est jugé très concerné. */
    public const SEUIL_CONCENTRATION = 35;

    /** Une demande « déposée » depuis plus longtemps est signalée comme en attente. */
    public const JOURS_ATTENTE = 7;

    /** Libellé des signalements dont l'auteur n'a pas indiqué de quartier (jamais cité comme quartier très concerné). */
    public const QUARTIER_INCONNU = 'Quartier non renseigné';

    public readonly string $periode;

    /** Vrai si la période personnalisée demandée était invalide (les 30 derniers jours sont alors utilisés). */
    public readonly bool $periodeIgnoree;

    public readonly int $jours;

    public readonly CarbonImmutable $debut;

    public readonly CarbonImmutable $fin;

    public readonly CarbonImmutable $debutPrecedent;

    public readonly CarbonImmutable $finPrecedente;

    /** @var array<string, mixed> Résultats déjà calculés (un rapport = une seule série de requêtes). */
    private array $memo = [];

    public function __construct(string $periode = '30', ?string $du = null, ?string $au = null)
    {
        $aujourdhui = CarbonImmutable::today();
        $debut = self::date($du);
        $fin = self::date($au);

        $personnalisee = $periode === 'perso' && $debut !== null && $fin !== null
            && $debut->lte($fin) && $fin->lte($aujourdhui) && $debut->diffInDays($fin) < self::JOURS_MAXIMUM;

        if (! $personnalisee) {
            $jours = $periode === '7' ? 7 : 30;
            $fin = $aujourdhui;
            $debut = $aujourdhui->subDays($jours - 1);
        }

        $this->periodeIgnoree = $periode === 'perso' && ! $personnalisee;
        $this->periode = $personnalisee ? 'perso' : ($periode === '7' ? '7' : '30');
        $this->debut = $debut->startOfDay();
        $this->fin = $fin->endOfDay();
        $this->jours = (int) $this->debut->diffInDays($fin->startOfDay()) + 1;
        $this->finPrecedente = $this->debut->subSecond();
        $this->debutPrecedent = $this->debut->subDays($this->jours);
    }

    /**
     * Chiffres clés de la période et de la période précédente.
     *
     * @return array{demandes: int, demandes_avant: int, evolution_demandes: int|null, traitees: int, taux_traitement: int|null, taux_traitement_avant: int|null, clotures: int, delai: float|null, delai_avant: float|null, evolution_delai: int|null, signalements: int, signalements_avant: int, evolution_signalements: int|null, signalements_resolus: int, urgences: int, urgences_avant: int, urgences_ouvertes: int, urgences_sans_prise_en_charge: int, en_attente: int}
     */
    public function chiffres(): array
    {
        return $this->memo['chiffres'] ??= (function (): array {
            [$demandes, $demandesAvant] = $this->demandes();
            [$clotures, $cloturesAvant] = $this->clotures();
            [$signalements, $signalementsAvant] = $this->signalements();

            $traitees = $demandes->whereIn('statut', self::STATUTS_TERMINES)->count();
            $traiteesAvant = $demandesAvant->whereIn('statut', self::STATUTS_TERMINES)->count();
            $urgences = $demandes->filter(fn (stdClass $d): bool => (bool) $d->urgence_medicale);
            $urgencesOuvertes = $urgences->whereIn('statut', Demarche::STATUTS_URGENCE_OUVERTE);
            $delai = self::delaiMoyen($clotures);
            $delaiAvant = self::delaiMoyen($cloturesAvant);

            return [
                'demandes' => $demandes->count(),
                'demandes_avant' => $demandesAvant->count(),
                'evolution_demandes' => UsageServices::evolution($demandes->count(), $demandesAvant->count()),
                'traitees' => $traitees,
                'taux_traitement' => $demandes->isEmpty() ? null : (int) round($traitees * 100 / $demandes->count()),
                'taux_traitement_avant' => $demandesAvant->isEmpty() ? null : (int) round($traiteesAvant * 100 / $demandesAvant->count()),
                'clotures' => $clotures->count(),
                'delai' => $delai,
                'delai_avant' => $delaiAvant,
                'evolution_delai' => self::evolutionDelai($delai, $delaiAvant),
                'signalements' => $signalements->count(),
                'signalements_avant' => $signalementsAvant->count(),
                'evolution_signalements' => UsageServices::evolution($signalements->count(), $signalementsAvant->count()),
                'signalements_resolus' => $signalements->where('statut', 'resolu')->count(),
                'urgences' => $urgences->count(),
                'urgences_avant' => $demandesAvant->filter(fn (stdClass $d): bool => (bool) $d->urgence_medicale)->count(),
                'urgences_ouvertes' => $urgencesOuvertes->count(),
                'urgences_sans_prise_en_charge' => $urgencesOuvertes->whereNull('pris_en_charge_le')->count(),
                'en_attente' => (int) $this->enAttenteParService()->sum(),
            ];
        })();
    }

    /**
     * Services, du plus demandé au moins demandé (ceux sans activité sur les deux périodes sont omis).
     *
     * @return Collection<int, LigneService>
     */
    public function services(): Collection
    {
        return $this->memo['services'] ??= (function (): Collection {
            [$demandes, $demandesAvant] = array_map(fn (Collection $c): Collection => $c->groupBy('service_id'), $this->demandes());
            [$clotures, $cloturesAvant] = array_map(fn (Collection $c): Collection => $c->groupBy('service_id'), $this->clotures());
            $noms = $this->nomsServices();

            return $demandes->keys()->merge($demandesAvant->keys())->merge($clotures->keys())->unique()
                ->map(function (mixed $id) use ($demandes, $demandesAvant, $clotures, $cloturesAvant, $noms): array {
                    $lignes = $demandes->get($id, collect());
                    $avant = $demandesAvant->get($id, collect())->count();
                    $delai = self::delaiMoyen($clotures->get($id, collect()));
                    $delaiAvant = self::delaiMoyen($cloturesAvant->get($id, collect()));
                    $volumeSuffisant = $clotures->get($id, collect())->count() >= self::VOLUME_MINIMAL
                        && $cloturesAvant->get($id, collect())->count() >= self::VOLUME_MINIMAL;

                    return [
                        'nom' => (string) ($noms[$id] ?? 'Sans service précisé'),
                        'demandes' => $lignes->count(),
                        'demandes_avant' => $avant,
                        'evolution' => UsageServices::evolution($lignes->count(), $avant),
                        'traitees' => $lignes->whereIn('statut', self::STATUTS_TERMINES)->count(),
                        'delai' => $delai,
                        'delai_avant' => $delaiAvant,
                        'evolution_delai' => $volumeSuffisant ? self::evolutionDelai($delai, $delaiAvant) : null,
                    ];
                })
                ->sortByDesc(fn (array $l): array => [$l['demandes'], $l['demandes_avant']])
                ->values();
        })();
    }

    /**
     * Signalements par quartier (quartier de résidence de l'auteur), du plus concerné au moins concerné.
     *
     * @return Collection<int, LigneQuartier>
     */
    public function quartiers(): Collection
    {
        return $this->memo['quartiers'] ??= (function (): Collection {
            [$signalements, $avant] = array_map(fn (Collection $c): Collection => $c->groupBy(fn (stdClass $s): string => $s->quartier ?? self::QUARTIER_INCONNU), $this->signalements());

            return $signalements
                ->map(fn (Collection $lignes, string $nom): array => [
                    'nom' => $nom,
                    'signalements' => $lignes->count(),
                    'ouverts' => $lignes->whereIn('statut', Signalement::STATUTS_OUVERTS)->count(),
                    'avant' => $avant->get($nom, collect())->count(),
                ])
                ->sortByDesc('signalements')
                ->values();
        })();
    }

    /**
     * Synthèse du rapport en phrases lisibles.
     *
     * @return list<string>
     */
    public function synthese(): array
    {
        $c = $this->chiffres();

        if ($c['demandes'] + $c['signalements'] + $c['clotures'] === 0) {
            return [sprintf('Du %s au %s, aucune demande ni aucun signalement n’a été enregistré sur la plateforme. Choisissez une période plus large.', $this->debut->format('d/m/Y'), $this->fin->format('d/m/Y'))];
        }

        $phrases = [sprintf(
            'Du %s au %s (%d jour%s), la plateforme a reçu %d demande%s d’habitants%s.',
            $this->debut->format('d/m/Y'), $this->fin->format('d/m/Y'), $this->jours, $this->jours > 1 ? 's' : '',
            $c['demandes'], $c['demandes'] > 1 ? 's' : '',
            $c['evolution_demandes'] === null ? ' (aucune sur la période précédente : pas de comparaison possible)' : ' : '.self::tendance($c['evolution_demandes']).' par rapport à la période précédente ('.$c['demandes_avant'].')',
        )];

        if ($c['taux_traitement'] !== null) {
            $phrases[] = sprintf('%d %% de ces demandes ont déjà reçu une réponse (%d sur %d).', $c['taux_traitement'], $c['traitees'], $c['demandes'])
                .($c['delai'] === null ? ' Aucune demande n’a été clôturée sur la période : le délai moyen ne peut pas être calculé.' : sprintf(
                    ' Le délai moyen de traitement est de %s, calculé sur %d demande%s clôturée%s pendant la période%s.',
                    self::formatDelai($c['delai']), $c['clotures'], $c['clotures'] > 1 ? 's' : '', $c['clotures'] > 1 ? 's' : '',
                    $c['delai_avant'] === null ? '' : ' ('.self::tendance($c['evolution_delai']).' ; '.self::formatDelai($c['delai_avant']).' auparavant)',
                ));
        }

        $top = $this->services()->where('demandes', '>', 0)->take(3);

        if ($top->isNotEmpty()) {
            $phrases[] = 'Services les plus demandés : '.$top->map(fn (array $l): string => $l['nom'].' ('.$l['demandes'].')')->implode(', ').'.';
        }

        $phrases[] = $c['signalements'] === 0
            ? 'Aucun signalement n’a été déposé sur la période.'
            : sprintf(
                '%d signalement%s déposé%s (%s), dont %d déjà résolu%s. Par quartier : %s.',
                $c['signalements'], $c['signalements'] > 1 ? 's ont été' : ' a été', $c['signalements'] > 1 ? 's' : '',
                self::tendance($c['evolution_signalements']), $c['signalements_resolus'], $c['signalements_resolus'] > 1 ? 's' : '',
                $this->quartiers()->map(fn (array $q): string => $q['nom'].' ('.$q['signalements'].')')->implode(', '),
            );

        $phrases[] = $c['urgences'] === 0
            ? 'Aucune urgence médicale n’a été signalée sur la période.'
            : sprintf(
                '%d urgence%s médicale%s signalée%s (%d sur la période précédente) : %d encore ouverte%s, dont %d sans prise en charge.',
                $c['urgences'], $c['urgences'] > 1 ? 's' : '', $c['urgences'] > 1 ? 's' : '', $c['urgences'] > 1 ? 's' : '',
                $c['urgences_avant'], $c['urgences_ouvertes'], $c['urgences_ouvertes'] > 1 ? 's' : '', $c['urgences_sans_prise_en_charge'],
            );

        return $phrases;
    }

    /**
     * 3 à 5 points d'attention, du plus important au moins important (niveaux : alerte, attention, info, succes).
     *
     * @return list<Point>
     */
    public function pointsAttention(): array
    {
        $c = $this->chiffres();
        $services = $this->services();
        $points = [];

        if ($c['urgences_sans_prise_en_charge'] > 0) {
            $points[] = ['niveau' => 'alerte', 'texte' => sprintf('%d urgence(s) médicale(s) de la période attendent encore une prise en charge par un agent.', $c['urgences_sans_prise_en_charge'])];
        }

        foreach ($services->filter(fn (array $l): bool => $l['evolution_delai'] !== null && $l['evolution_delai'] >= self::SEUIL_VARIATION)->sortByDesc('evolution_delai')->take(2) as $l) {
            $points[] = ['niveau' => 'attention', 'texte' => sprintf('Le délai moyen a augmenté de %d %% pour « %s » (%s, contre %s sur la période précédente).', $l['evolution_delai'], $l['nom'], self::formatDelai($l['delai']), self::formatDelai($l['delai_avant']))];
        }

        if ($c['en_attente'] > 0) {
            $attente = $this->enAttenteParService();
            $points[] = ['niveau' => 'attention', 'texte' => sprintf('%d demande(s) attendent une prise en charge depuis plus de %d jours, dont %d pour « %s ».', $c['en_attente'], self::JOURS_ATTENTE, (int) $attente->first(), (string) ($this->nomsServices()[$attente->keys()->first()] ?? 'Sans service précisé'))];
        }

        if ($c['taux_traitement'] !== null && $c['demandes'] >= self::VOLUME_MINIMAL && $c['taux_traitement'] < self::SEUIL_TRAITEMENT) {
            $points[] = ['niveau' => 'attention', 'texte' => sprintf('Seules %d %% des demandes de la période ont reçu une réponse (%d sur %d) : le traitement ne suit pas le rythme des dépôts.', $c['taux_traitement'], $c['traitees'], $c['demandes'])];
        }

        $quartier = $this->quartiers()->firstWhere('nom', '!=', self::QUARTIER_INCONNU);

        if ($quartier !== null && $quartier['signalements'] >= self::VOLUME_MINIMAL && self::SEUIL_CONCENTRATION * $c['signalements'] <= $quartier['signalements'] * 100) {
            $points[] = ['niveau' => 'attention', 'texte' => sprintf('Le quartier « %s » concentre %d %% des signalements (%d sur %d, dont %d encore ouvert(s)).', $quartier['nom'], (int) round($quartier['signalements'] * 100 / $c['signalements']), $quartier['signalements'], $c['signalements'], $quartier['ouverts'])];
        }

        if ($c['evolution_demandes'] !== null && abs($c['evolution_demandes']) >= self::SEUIL_VARIATION && $c['demandes'] + $c['demandes_avant'] >= self::VOLUME_MINIMAL) {
            $points[] = ['niveau' => $c['evolution_demandes'] > 0 ? 'attention' : 'info', 'texte' => sprintf('Le nombre de demandes a %s de %d %% par rapport à la période précédente (%d contre %d).', $c['evolution_demandes'] > 0 ? 'augmenté' : 'baissé', abs($c['evolution_demandes']), $c['demandes'], $c['demandes_avant'])];
        }

        $hausse = $services->filter(fn (array $l): bool => $l['evolution'] !== null && $l['evolution'] >= self::SEUIL_VARIATION && $l['demandes'] >= self::VOLUME_MINIMAL)->sortByDesc('evolution')->first();

        if ($hausse !== null) {
            $points[] = ['niveau' => 'info', 'texte' => sprintf('« %s » est le service dont la demande progresse le plus : %s (%d demandes contre %d).', $hausse['nom'], UsageServices::pourcentage($hausse['evolution']), $hausse['demandes'], $hausse['demandes_avant'])];
        }

        if ($c['evolution_delai'] !== null && $c['evolution_delai'] <= -self::SEUIL_VARIATION) {
            $points[] = ['niveau' => 'succes', 'texte' => sprintf('Le délai moyen de traitement a baissé de %d %% (%s, contre %s).', abs($c['evolution_delai']), self::formatDelai($c['delai']), self::formatDelai($c['delai_avant']))];
        }

        // Compléments pour toujours présenter au moins 3 points, même sur une période calme.
        $premier = $services->first();
        $complements = [
            $premier !== null && $premier['demandes'] > 0
                ? ['niveau' => 'info', 'texte' => sprintf('« %s » est le service le plus demandé : %d demande(s), soit %d %% du total.', $premier['nom'], $premier['demandes'], (int) round($premier['demandes'] * 100 / max($c['demandes'], 1)))]
                : ['niveau' => 'info', 'texte' => 'Aucune demande n’a été déposée sur la période : choisissez une période plus large pour une analyse utile.'],
            $c['urgences_ouvertes'] === 0
                ? ['niveau' => 'succes', 'texte' => 'Aucune urgence médicale de la période ne reste ouverte.']
                : ['niveau' => 'info', 'texte' => sprintf('%d urgence(s) médicale(s) de la période sont encore ouvertes, toutes prises en charge par un agent.', $c['urgences_ouvertes'])],
            ['niveau' => 'info', 'texte' => sprintf('Aucun autre écart de plus de %d %% n’a été relevé par rapport à la période précédente.', self::SEUIL_VARIATION)],
        ];

        foreach ($complements as $complement) {
            if (count($points) >= 3) {
                break;
            }

            $points[] = $complement;
        }

        return array_slice($points, 0, 5);
    }

    /**
     * Chiffres du rapport au format CSV pour Excel (BOM UTF-8, séparateur « ; »).
     */
    public function csv(): string
    {
        $c = $this->chiffres();

        $lignes = [
            ['Rapport d’activité du '.$this->debut->format('d/m/Y').' au '.$this->fin->format('d/m/Y')],
            ['Période précédente du '.$this->debutPrecedent->format('d/m/Y').' au '.$this->finPrecedente->format('d/m/Y')],
            [],
            ['Indicateur', 'Période', 'Période précédente', 'Évolution (%)'],
            ['Demandes déposées', $c['demandes'], $c['demandes_avant'], $c['evolution_demandes']],
            ['Taux de traitement (%)', $c['taux_traitement'], $c['taux_traitement_avant'], null],
            ['Délai moyen de traitement (jours)', self::nombre($c['delai']), self::nombre($c['delai_avant']), $c['evolution_delai']],
            ['Signalements déposés', $c['signalements'], $c['signalements_avant'], $c['evolution_signalements']],
            ['Urgences médicales', $c['urgences'], $c['urgences_avant'], UsageServices::evolution($c['urgences'], $c['urgences_avant'])],
            ['Urgences encore ouvertes', $c['urgences_ouvertes'], null, null],
            ['Demandes en attente depuis plus de '.self::JOURS_ATTENTE.' jours', $c['en_attente'], null, null],
            [],
            ['Service', 'Demandes', 'Demandes (période précédente)', 'Évolution (%)', 'Demandes traitées', 'Délai moyen (jours)', 'Délai moyen précédent (jours)'],
        ];

        foreach ($this->services() as $l) {
            $lignes[] = [$l['nom'], $l['demandes'], $l['demandes_avant'], $l['evolution'], $l['traitees'], self::nombre($l['delai']), self::nombre($l['delai_avant'])];
        }

        $lignes[] = [];
        $lignes[] = ['Quartier', 'Signalements', 'Signalements encore ouverts', 'Signalements (période précédente)'];

        foreach ($this->quartiers() as $q) {
            $lignes[] = [$q['nom'], $q['signalements'], $q['ouverts'], $q['avant']];
        }

        $lignes[] = [];
        $lignes[] = ['Points d’attention'];

        foreach ($this->pointsAttention() as $point) {
            $lignes[] = [$point['texte']];
        }

        return "\xEF\xBB\xBF".collect($lignes)->map(fn (array $ligne): string => self::ligneCsv($ligne))->implode('');
    }

    /**
     * Variation lisible : « ▲ 12 % », « ▼ 8 % », « stable » ou « sans comparaison possible ».
     */
    public static function tendance(?int $evolution): string
    {
        return match (true) {
            $evolution === null => 'sans comparaison possible',
            $evolution > 0 => '▲ '.$evolution.' %',
            $evolution < 0 => '▼ '.abs($evolution).' %',
            default => 'stable',
        };
    }

    public static function formatDelai(?float $jours): string
    {
        return match (true) {
            $jours === null => '—',
            $jours < 1 => 'moins d’un jour',
            default => self::nombre($jours).' jour'.($jours >= 2 ? 's' : ''),
        };
    }

    private static function nombre(?float $valeur): ?string
    {
        return $valeur === null ? null : str_replace('.', ',', (string) $valeur);
    }

    private static function evolutionDelai(?float $actuel, ?float $precedent): ?int
    {
        return $actuel !== null && $precedent !== null && $precedent > 0 ? (int) round(($actuel - $precedent) * 100 / $precedent) : null;
    }

    /**
     * Délai moyen (en jours) du dépôt à la dernière mise à jour, ou null sans demande clôturée.
     *
     * @param  Collection<int, stdClass>  $clotures
     */
    private static function delaiMoyen(Collection $clotures): ?float
    {
        return $clotures->isEmpty() ? null : round((float) $clotures->avg(fn (stdClass $d): float => max(0, strtotime((string) $d->updated_at) - strtotime((string) $d->created_at)) / 86400), 1);
    }

    /**
     * Date « AAAA-MM-JJ » venue du navigateur, ou null si elle est absente ou invalide.
     */
    private static function date(?string $valeur): ?CarbonImmutable
    {
        if ($valeur === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valeur, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return CarbonImmutable::create((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    /**
     * Demandes déposées sur la période et sur la période précédente.
     *
     * @return array{0: Collection<int, stdClass>, 1: Collection<int, stdClass>}
     */
    private function demandes(): array
    {
        return $this->memo['demandes'] ??= $this->separer(
            Demarche::query()->toBase()
                ->whereBetween('created_at', [$this->debutPrecedent, $this->fin])
                ->get(['service_id', 'statut', 'created_at', 'urgence_medicale', 'pris_en_charge_le']),
            'created_at',
        );
    }

    /**
     * Demandes clôturées (traitées ou refusées) sur la période et sur la période précédente.
     *
     * @return array{0: Collection<int, stdClass>, 1: Collection<int, stdClass>}
     */
    private function clotures(): array
    {
        return $this->memo['clotures'] ??= $this->separer(
            Demarche::query()->toBase()
                ->whereIn('statut', self::STATUTS_TERMINES)
                ->whereBetween('updated_at', [$this->debutPrecedent, $this->fin])
                ->get(['service_id', 'created_at', 'updated_at']),
            'updated_at',
        );
    }

    /**
     * Signalements déposés sur la période et sur la période précédente, avec le quartier de leur auteur.
     *
     * @return array{0: Collection<int, stdClass>, 1: Collection<int, stdClass>}
     */
    private function signalements(): array
    {
        return $this->memo['signalements'] ??= $this->separer(
            Signalement::query()->toBase()
                ->leftJoin('users', 'users.id', '=', 'signalements.user_id')
                ->leftJoin('quartiers', 'quartiers.id', '=', 'users.quartier_id')
                ->whereBetween('signalements.created_at', [$this->debutPrecedent, $this->fin])
                ->get(['quartiers.nom as quartier', 'signalements.statut', 'signalements.created_at']),
            'created_at',
        );
    }

    /**
     * Demandes « déposées » depuis plus de JOURS_ATTENTE jours, par service (le plus chargé en premier).
     *
     * @return Collection<int|string, mixed>
     */
    private function enAttenteParService(): Collection
    {
        return $this->memo['en_attente'] ??= Demarche::query()->toBase()
            ->where('statut', 'deposee')
            ->where('created_at', '<=', now()->subDays(self::JOURS_ATTENTE))
            ->selectRaw('service_id, count(*) as total')
            ->groupBy('service_id')
            ->orderByDesc('total')
            ->pluck('total', 'service_id');
    }

    /**
     * @return Collection<int|string, mixed>
     */
    private function nomsServices(): Collection
    {
        return $this->memo['noms'] ??= Service::query()->pluck('nom', 'id');
    }

    /**
     * Sépare des lignes entre la période et la période précédente, selon une colonne de date.
     *
     * @param  Collection<int, stdClass>  $lignes
     * @return array{0: Collection<int, stdClass>, 1: Collection<int, stdClass>}
     */
    private function separer(Collection $lignes, string $colonne): array
    {
        $debut = $this->debut->toDateTimeString();

        [$periode, $avant] = $lignes->partition(fn (stdClass $ligne): bool => (string) $ligne->{$colonne} >= $debut);

        return [$periode->values(), $avant->values()];
    }

    /**
     * Ligne CSV. Un texte commençant par = + - @ est neutralisé (injection CSV).
     *
     * @param  array<int, mixed>  $valeurs
     */
    private static function ligneCsv(array $valeurs): string
    {
        return collect($valeurs)
            ->map(function (mixed $valeur): string {
                $texte = is_scalar($valeur) ? (string) $valeur : '';

                if (is_string($valeur) && $texte !== '' && str_contains("=+-@\t\r", $texte[0])) {
                    $texte = "'".$texte;
                }

                return '"'.str_replace('"', '""', $texte).'"';
            })
            ->implode(';')."\n";
    }
}
