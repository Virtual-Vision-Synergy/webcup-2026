<?php

namespace App\Services;

use App\Models\Demarche;
use App\Models\Service;
use App\Models\VueService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * F98 : usage des services par les habitants (consultations anonymisées, démarches lancées, terminées, abandonnées),
 * sur une période, avec comparaison à la période précédente et phrases d'analyse générées par règles (sans IA).
 *
 * Filtres en LISTE BLANCHE : une période, un quartier ou une catégorie inconnus sont ignorés.
 *
 * @phpstan-type LigneUsage array{service_id: int, nom: string, categorie: string, consultations: int, lancees: int, terminees: int, abandonnees: int, taux_abandon: int|null, consultations_avant: int, lancees_avant: int, evolution: int|null}
 */
class UsageServices
{
    /** @var array<int, string> Périodes proposées : nombre de jours => libellé. */
    public const PERIODES = [7 => '7 derniers jours', 30 => '30 derniers jours', 90 => '90 derniers jours'];

    /** Une démarche encore « déposée » après ce délai est considérée comme abandonnée (jamais prise en main). */
    public const JOURS_AVANT_ABANDON = 14;

    /** Taux d'abandon (en %) à partir duquel une phrase d'alerte est générée. */
    public const SEUIL_ABANDON = 25;

    /** Volume minimal pour qu'un pourcentage soit jugé significatif (évite « +100 % » sur 1 démarche). */
    public const VOLUME_MINIMAL = 5;

    public readonly int $jours;

    public readonly ?int $quartierId;

    public readonly ?string $categorie;

    public readonly CarbonInterface $debut;

    public readonly CarbonInterface $fin;

    public readonly CarbonInterface $debutPrecedent;

    public readonly CarbonInterface $finPrecedente;

    public function __construct(int $jours = 30, ?int $quartierId = null, ?string $categorie = null)
    {
        $this->jours = array_key_exists($jours, self::PERIODES) ? $jours : 30;
        $this->quartierId = $quartierId;
        $this->categorie = in_array($categorie, Service::CATEGORIE_OPTIONS, true) ? $categorie : null;

        $this->fin = Carbon::today()->endOfDay();
        $this->debut = Carbon::today()->subDays($this->jours - 1)->startOfDay();
        $this->finPrecedente = $this->debut->copy()->subSecond();
        $this->debutPrecedent = $this->debut->copy()->subDays($this->jours);
    }

    /**
     * Classement des services, du plus sollicité au moins sollicité.
     *
     * @return Collection<int, LigneUsage>
     */
    public function classement(): Collection
    {
        $services = Service::query()
            ->when($this->categorie, fn (Builder $q, string $categorie) => $q->where('categorie', $categorie))
            ->orderBy('nom')
            ->get(['id', 'nom', 'categorie']);

        $vues = $this->consultationsParService($this->debut, $this->fin);
        $vuesAvant = $this->consultationsParService($this->debutPrecedent, $this->finPrecedente);
        $demarches = $this->demarchesParService($this->debut, $this->fin);
        $lanceesAvant = $this->demarchesParService($this->debutPrecedent, $this->finPrecedente);

        $classement = $services
            ->map(function (Service $service) use ($vues, $vuesAvant, $demarches, $lanceesAvant): array {
                $stats = $demarches->get($service->id);
                $lancees = (int) ($stats->lancees ?? 0);
                $abandonnees = (int) ($stats->abandonnees ?? 0);
                $consultations = (int) $vues->get($service->id, 0);
                $consultationsAvant = (int) $vuesAvant->get($service->id, 0);
                $avant = (int) ($lanceesAvant->get($service->id)->lancees ?? 0);

                return [
                    'service_id' => $service->id,
                    'nom' => $service->nom,
                    'categorie' => (string) $service->categorie,
                    'consultations' => $consultations,
                    'lancees' => $lancees,
                    'terminees' => (int) ($stats->terminees ?? 0),
                    'abandonnees' => $abandonnees,
                    'taux_abandon' => $lancees > 0 ? (int) round($abandonnees * 100 / $lancees) : null,
                    'consultations_avant' => $consultationsAvant,
                    'lancees_avant' => $avant,
                    'evolution' => self::evolution($consultations + $lancees, $consultationsAvant + $avant),
                ];
            })
            ->sortByDesc(fn (array $ligne): array => [$ligne['lancees'] + $ligne['consultations'], $ligne['lancees']])
            ->values();

        /** @var Collection<int, LigneUsage> $classement */
        return $classement;
    }

    /**
     * Évolution jour par jour (consultations et démarches lancées) sur la période.
     *
     * @return list<array{jour: string, libelle: string, consultations: int, lancees: int}>
     */
    public function evolutionQuotidienne(): array
    {
        $vues = $this->requeteVues($this->debut, $this->fin)
            ->selectRaw('DATE(jour) as d, SUM(nombre) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $demarches = $this->requeteDemarches($this->debut, $this->fin)
            ->selectRaw('DATE(demarches.created_at) as d, COUNT(*) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $points = [];

        for ($jour = $this->debut->copy(); $jour->lte($this->fin); $jour = $jour->addDay()) {
            $cle = $jour->toDateString();
            $points[] = [
                'jour' => $cle,
                'libelle' => $jour->translatedFormat('d M'),
                'consultations' => (int) ($vues[$cle] ?? 0),
                'lancees' => (int) ($demarches[$cle] ?? 0),
            ];
        }

        return $points;
    }

    /**
     * Totaux de la période et évolution par rapport à la période précédente.
     *
     * @param  Collection<int, LigneUsage>  $classement
     * @return array{consultations: int, lancees: int, terminees: int, abandonnees: int, taux_abandon: int|null, evolution: int|null}
     */
    public function totaux(Collection $classement): array
    {
        $lancees = (int) $classement->sum('lancees');
        $abandonnees = (int) $classement->sum('abandonnees');
        $consultations = (int) $classement->sum('consultations');

        return [
            'consultations' => $consultations,
            'lancees' => $lancees,
            'terminees' => (int) $classement->sum('terminees'),
            'abandonnees' => $abandonnees,
            'taux_abandon' => $lancees > 0 ? (int) round($abandonnees * 100 / $lancees) : null,
            'evolution' => self::evolution(
                $consultations + $lancees,
                (int) $classement->sum('consultations_avant') + (int) $classement->sum('lancees_avant'),
            ),
        ];
    }

    /**
     * Phrases d'analyse exploitables, générées par règles (pas d'IA) : ce qu'un élu peut retenir en 10 secondes.
     *
     * @param  Collection<int, LigneUsage>  $classement
     * @return list<array{niveau: string, texte: string}>
     */
    public function analyses(Collection $classement): array
    {
        $phrases = [];
        $periode = $this->jours === 7 ? 'la semaine précédente' : 'les '.$this->jours.' jours précédents';
        $actifs = $classement->filter(fn (array $l): bool => $l['lancees'] + $l['consultations'] > 0);

        if ($actifs->isEmpty()) {
            return [['niveau' => 'info', 'texte' => 'Aucune consultation ni démarche sur la période et les filtres choisis.']];
        }

        $totaux = $this->totaux($classement);

        if ($totaux['evolution'] !== null) {
            $phrases[] = [
                'niveau' => 'info',
                'texte' => sprintf('Activité globale : %s vs %s (%d consultations, %d démarches lancées).',
                    self::pourcentage($totaux['evolution']), $periode, $totaux['consultations'], $totaux['lancees']),
            ];
        }

        $premier = $classement->sortByDesc('lancees')->first();

        if ($premier !== null && $premier['lancees'] > 0) {
            $part = $totaux['lancees'] > 0 ? (int) round($premier['lancees'] * 100 / $totaux['lancees']) : 0;
            $tendance = self::evolution($premier['lancees'], $premier['lancees_avant']);
            $phrases[] = [
                'niveau' => 'succes',
                'texte' => sprintf('Le service « %s » est le plus demandé : %d démarches lancées, soit %d %% du total%s.',
                    $premier['nom'], $premier['lancees'], $part,
                    $tendance !== null && $premier['lancees_avant'] >= self::VOLUME_MINIMAL ? ' ('.self::pourcentage($tendance).' vs '.$periode.')' : ''),
            ];
        }

        $plusConsulte = $classement->sortByDesc('consultations')->first();

        if ($plusConsulte !== null && $plusConsulte['consultations'] > 0 && $plusConsulte['service_id'] !== ($premier['service_id'] ?? null)) {
            $phrases[] = [
                'niveau' => 'info',
                'texte' => sprintf('« %s » est le service le plus consulté en ligne (%d consultations).', $plusConsulte['nom'], $plusConsulte['consultations']),
            ];
        }

        $hausse = $classement
            ->filter(fn (array $l): bool => $l['consultations_avant'] + $l['lancees_avant'] >= self::VOLUME_MINIMAL && ($l['evolution'] ?? 0) >= 20)
            ->sortByDesc('evolution')
            ->first();

        if ($hausse !== null) {
            $phrases[] = [
                'niveau' => 'attention',
                'texte' => sprintf('Forte hausse pour « %s » : %s vs %s. Prévoir des agents ou des créneaux supplémentaires.',
                    $hausse['nom'], self::pourcentage($hausse['evolution']), $periode),
            ];
        }

        $classement
            ->filter(fn (array $l): bool => $l['lancees'] >= self::VOLUME_MINIMAL && $l['taux_abandon'] >= self::SEUIL_ABANDON)
            ->sortByDesc('taux_abandon')
            ->take(2)
            ->each(function (array $l) use (&$phrases): void {
                $phrases[] = [
                    'niveau' => 'alerte',
                    'texte' => sprintf('%d %% des démarches « %s » sont abandonnées ou refusées (%d sur %d) : parcours ou pièces demandées à revoir.',
                        $l['taux_abandon'], $l['nom'], $l['abandonnees'], $l['lancees']),
                ];
            });

        $peuConverti = $classement
            ->filter(fn (array $l): bool => $l['service_id'] !== ($premier['service_id'] ?? null) && $l['lancees'] > 0 && $l['consultations'] >= 20 && $l['consultations'] > $l['lancees'] * 15)
            ->sortByDesc('consultations')
            ->first();

        if ($peuConverti !== null) {
            $phrases[] = [
                'niveau' => 'attention',
                'texte' => sprintf('« %s » est très consulté (%d consultations) mais ne génère que %d démarche(s) : l’information en ligne suffit peut-être, ou le formulaire décourage.',
                    $peuConverti['nom'], $peuConverti['consultations'], $peuConverti['lancees']),
            ];
        }

        $inutilises = $classement->filter(fn (array $l): bool => $l['lancees'] + $l['consultations'] === 0)->count();

        if ($inutilises > 0) {
            $phrases[] = [
                'niveau' => 'info',
                'texte' => sprintf('%d service(s) n’ont reçu ni consultation ni démarche sur la période : visibilité à améliorer.', $inutilises),
            ];
        }

        return $phrases;
    }

    /**
     * Variation en % entre deux volumes, ou null si la période précédente est vide.
     */
    public static function evolution(int $actuel, int $precedent): ?int
    {
        return $precedent > 0 ? (int) round(($actuel - $precedent) * 100 / $precedent) : null;
    }

    public static function pourcentage(int $valeur): string
    {
        return ($valeur > 0 ? '+' : '').$valeur.' %';
    }

    /**
     * @return Collection<int|string, mixed>
     */
    private function consultationsParService(CarbonInterface $debut, CarbonInterface $fin): Collection
    {
        return $this->requeteVues($debut, $fin)
            ->selectRaw('service_id, SUM(nombre) as total')
            ->groupBy('service_id')
            ->pluck('total', 'service_id');
    }

    /**
     * @return Collection<int|string, object{lancees: int, terminees: int, abandonnees: int}>
     */
    private function demarchesParService(CarbonInterface $debut, CarbonInterface $fin): Collection
    {
        $limiteAbandon = Carbon::now()->subDays(self::JOURS_AVANT_ABANDON);

        /** @var Collection<int|string, object{lancees: int, terminees: int, abandonnees: int}> */
        return $this->requeteDemarches($debut, $fin)
            ->select('demarches.service_id')
            ->selectRaw('COUNT(*) as lancees')
            ->selectRaw("SUM(CASE WHEN demarches.statut = 'traitee' THEN 1 ELSE 0 END) as terminees")
            ->selectRaw("SUM(CASE WHEN demarches.statut = 'refusee' OR (demarches.statut = 'deposee' AND demarches.created_at < ?) THEN 1 ELSE 0 END) as abandonnees", [$limiteAbandon])
            ->groupBy('demarches.service_id')
            ->get()
            ->keyBy('service_id');
    }

    /**
     * @return Builder<VueService>
     */
    private function requeteVues(CarbonInterface $debut, CarbonInterface $fin): Builder
    {
        return VueService::query()
            ->whereDate('jour', '>=', $debut->toDateString())
            ->whereDate('jour', '<=', $fin->toDateString())
            ->when($this->quartierId, fn (Builder $q, int $id) => $q->where('quartier_id', $id))
            ->when($this->categorie, fn (Builder $q, string $categorie) => $q->whereIn('service_id', Service::query()->select('id')->where('categorie', $categorie)));
    }

    /**
     * @return Builder<Demarche>
     */
    private function requeteDemarches(CarbonInterface $debut, CarbonInterface $fin): Builder
    {
        return Demarche::query()
            ->whereBetween('demarches.created_at', [$debut, $fin])
            ->whereNotNull('demarches.service_id')
            ->when($this->quartierId, fn (Builder $q, int $id) => $q->whereIn('demarches.user_id', DB::table('users')->select('id')->where('quartier_id', $id)))
            ->when($this->categorie, fn (Builder $q, string $categorie) => $q->whereIn('demarches.service_id', Service::query()->select('id')->where('categorie', $categorie)));
    }
}
