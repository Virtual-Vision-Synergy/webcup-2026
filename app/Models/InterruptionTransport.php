<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\InterruptionTransportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * F97 : interruption d'une ou plusieurs lignes de transport, déclarée depuis Filament, avec les solutions de remplacement
 * (navette, autre ligne, à pied, à vélo). Visible de debut à fin, puis disparaît d'elle-même.
 *
 * @property int $id
 * @property int $user_id
 * @property string $cause
 * @property string|null $arrets_touches Un arrêt par ligne ; vide = toute la ligne.
 * @property CarbonInterface $debut
 * @property CarbonInterface $fin
 * @property array<int, mixed>|null $solutions
 * @property CarbonInterface|null $notified_at Envoi des notifications ; assigné par NotifierInterruptionTransport uniquement.
 * @property-read Collection<int, LigneTransport> $lignes
 *
 * user_id et notified_at ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 */
#[Fillable(['cause', 'arrets_touches', 'debut', 'fin', 'solutions'])]
class InterruptionTransport extends Model
{
    /** @use HasFactory<InterruptionTransportFactory> */
    use HasFactory;

    protected $table = 'interruptions_transport';

    public const CACHE_KEY = 'transports.interruptions';

    public const SOLUTION_OPTIONS = ['navette', 'ligne', 'pied', 'velo', 'autre'];

    public const SOLUTION_LABELS = [
        'navette' => 'Navette de remplacement',
        'ligne' => 'Autre ligne',
        'pied' => 'À pied',
        'velo' => 'À vélo',
        'autre' => 'Autre solution',
    ];

    public const SOLUTION_ICONES = [
        'navette' => 'bus',
        'ligne' => 'arrows-right-left',
        'pied' => 'user',
        'velo' => 'map',
        'autre' => 'light-bulb',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => self::oublierCache());
        static::deleted(fn () => self::oublierCache());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<LigneTransport, $this>
     */
    public function lignes(): BelongsToMany
    {
        return $this->belongsToMany(LigneTransport::class, 'interruption_transport_ligne');
    }

    /**
     * Interruptions en cours : debut <= maintenant < fin.
     *
     * @param  Builder<InterruptionTransport>  $query
     */
    public function scopeEnCours(Builder $query): void
    {
        $query->where('debut', '<=', now())->where('fin', '>', now());
    }

    public function enCours(): bool
    {
        return $this->debut->lte(now()) && $this->fin->gt(now());
    }

    public function statut(): string
    {
        return match (true) {
            $this->fin->lte(now()) => 'Terminée',
            $this->debut->gt(now()) => 'Programmée',
            default => 'En cours',
        };
    }

    /**
     * Interruptions en cours pour le bandeau (accueil, tableau de bord, transports), mises en cache 60 s
     * (tableaux simples : config/cache.php interdit de désérialiser des objets), vidées à chaque modification.
     *
     * @return list<array{cause: string, lignes: list<string>, fin: string}>
     */
    public static function pourBandeau(): array
    {
        /** @var list<array{cause: string, lignes: list<string>, debut: string, fin: string}> $interruptions */
        $interruptions = Cache::remember(self::CACHE_KEY, 60, fn (): array => self::query()
            ->where('fin', '>', now())
            ->with('lignes:id,numero')
            ->orderBy('debut')
            ->limit(20)
            ->get()
            ->map(fn (InterruptionTransport $interruption): array => [
                'cause' => $interruption->cause,
                'lignes' => $interruption->lignes->map(fn (LigneTransport $ligne): string => (string) $ligne->numero)->values()->all(),
                'debut' => $interruption->debut->toIso8601String(),
                'fin' => $interruption->fin->toIso8601String(),
            ])
            ->all());

        $maintenant = now();

        return array_values(array_map(
            fn (array $i): array => ['cause' => $i['cause'], 'lignes' => $i['lignes'], 'fin' => $i['fin']],
            array_filter($interruptions, fn (array $i): bool => $i['lignes'] !== []
                && Carbon::parse($i['debut'])->lte($maintenant) && Carbon::parse($i['fin'])->gt($maintenant)),
        ));
    }

    /**
     * À appeler aussi après un changement des lignes (la table pivot ne déclenche pas d'évènement du modèle).
     */
    public static function oublierCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Arrêts non desservis ; liste vide = toute la ligne est interrompue.
     *
     * @return list<string>
     */
    public function listeArretsTouches(): array
    {
        return array_values(collect(preg_split('/\R/', (string) $this->arrets_touches) ?: [])
            ->map(fn (string $arret) => trim($arret))
            ->filter()
            ->all());
    }

    /**
     * L'arrêt habituel de l'habitant n'est plus desservi (ou toute la ligne est coupée).
     */
    public function toucheArret(?string $arret): bool
    {
        $touches = $this->listeArretsTouches();

        if ($touches === [] || $arret === null || $arret === '') {
            return true;
        }

        return in_array(mb_strtolower($arret), array_map('mb_strtolower', $touches), true);
    }

    /**
     * Solutions de remplacement prêtes à afficher, avec la ligne alternative éventuelle (une seule requête).
     *
     * @return list<array{type: string, label: string, icone: string, titre: string, description: string, horaires: string, ligne: LigneTransport|null, lat: float|null, lng: float|null}>
     */
    public function solutionsAffichables(): array
    {
        $solutions = collect($this->solutions ?? [])
            ->filter(fn ($s): bool => is_array($s) && trim((string) ($s['titre'] ?? '')) !== '');
        $ids = $solutions->pluck('ligne_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $lignes = $ids === [] ? collect() : LigneTransport::query()->whereKey($ids)->get()->keyBy('id');

        return array_values($solutions->map(function (array $s) use ($lignes): array {
            $type = in_array($s['type'] ?? null, self::SOLUTION_OPTIONS, true) ? (string) $s['type'] : 'autre';
            $coordonnees = is_numeric($s['latitude'] ?? null) && is_numeric($s['longitude'] ?? null);

            return [
                'type' => $type,
                'label' => self::SOLUTION_LABELS[$type],
                'icone' => self::SOLUTION_ICONES[$type],
                'titre' => trim((string) $s['titre']),
                'description' => trim((string) ($s['description'] ?? '')),
                'horaires' => trim((string) ($s['horaires'] ?? '')),
                'ligne' => empty($s['ligne_id']) ? null : self::ligneOuNull($lignes->get((int) $s['ligne_id'])),
                'lat' => $coordonnees ? (float) $s['latitude'] : null,
                'lng' => $coordonnees ? (float) $s['longitude'] : null,
            ];
        })->all());
    }

    private static function ligneOuNull(mixed $ligne): ?LigneTransport
    {
        return $ligne instanceof LigneTransport ? $ligne : null;
    }

    /**
     * « jusqu’au samedi 3 octobre à 18 h 30 » (heure de Madagascar).
     */
    public function libelleFin(): string
    {
        $fin = $this->fin->copy()->setTimezone(Annonce::FUSEAU)->settings(['locale' => 'fr']);

        return 'jusqu’au '.$fin->translatedFormat('l j F').' à '.$fin->format('G').' h'.($fin->minute > 0 ? ' '.$fin->format('i') : '');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'debut' => 'datetime',
            'fin' => 'datetime',
            'notified_at' => 'datetime',
            'solutions' => 'array',
        ];
    }
}
