<?php

namespace App\Models;

use Database\Factories\AlerteCaniculeFactory;
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
 * F31 : alerte canicule de l'Agence sanitaire, ciblée sur un ou plusieurs quartiers (secteurs), sans IA.
 * Les habitants des quartiers touchés la voient en bandeau et la reçoivent dans la cloche (e-mail selon leur préférence).
 *
 * @property int $id
 * @property int $user_id
 * @property string $niveau
 * @property int|null $temperature_max
 * @property Carbon $debut
 * @property Carbon $fin
 * @property string $message
 * @property Carbon|null $notified_at Envoi des notifications ; assigné par NotifierCanicule uniquement.
 * @property-read User $user
 * @property-read Collection<int, Quartier> $quartiers
 *
 * user_id et notified_at ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 */
#[Fillable(['niveau', 'temperature_max', 'debut', 'fin', 'message'])]
class AlerteCanicule extends Model
{
    /** @use HasFactory<AlerteCaniculeFactory> */
    use HasFactory;

    protected $table = 'alertes_canicule';

    public const CACHE_KEY = 'canicule.en-diffusion';

    protected static function booted(): void
    {
        static::saved(fn () => self::oublierCache());
        static::deleted(fn () => self::oublierCache());
    }

    /** Du moins au plus grave. */
    public const NIVEAU_OPTIONS = ['vigilance', 'alerte', 'urgence'];

    public const NIVEAU_LABELS = [
        'vigilance' => 'Vigilance',
        'alerte' => 'Alerte',
        'urgence' => 'Urgence',
    ];

    public const PROFIL_OPTIONS = ['tout_public', 'personnes_agees', 'enfants', 'femmes_enceintes', 'malades_chroniques', 'travailleurs_exterieur'];

    public const PROFIL_LABELS = [
        'tout_public' => 'Tout public',
        'personnes_agees' => 'Personnes âgées',
        'enfants' => 'Enfants et nourrissons',
        'femmes_enceintes' => 'Femmes enceintes',
        'malades_chroniques' => 'Malades chroniques',
        'travailleurs_exterieur' => 'Travailleurs en extérieur',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Quartier, $this>
     */
    public function quartiers(): BelongsToMany
    {
        return $this->belongsToMany(Quartier::class, 'alerte_canicule_quartier');
    }

    /**
     * Alertes en cours : debut <= maintenant < fin.
     *
     * @param  Builder<AlerteCanicule>  $query
     */
    public function scopeEnCours(Builder $query): void
    {
        $query->where('debut', '<=', now())->where('fin', '>', now());
    }

    /**
     * Alerte en cours la plus grave pour le quartier de l'habitant, pour le bandeau de chaque page.
     * Aucune requête pour un visiteur ou un habitant sans quartier ; sinon une liste courte mise en cache 60 s
     * (tableaux bruts : config/cache.php interdit de désérialiser des objets), vidée à chaque modification.
     *
     * @return array{niveau: string, message: string}|null
     */
    public static function pourBandeau(?User $habitant): ?array
    {
        if ($habitant?->quartier_id === null) {
            return null;
        }

        /** @var list<array{niveau: string, message: string, debut: string, fin: string, quartiers: list<int>}> $alertes */
        $alertes = Cache::remember(self::CACHE_KEY, 60, fn (): array => self::query()
            ->where('fin', '>', now())
            ->with('quartiers:id')
            ->get()
            ->map(fn (AlerteCanicule $alerte): array => [
                'niveau' => $alerte->niveau,
                'message' => $alerte->message,
                'debut' => $alerte->debut->toIso8601String(),
                'fin' => $alerte->fin->toIso8601String(),
                'quartiers' => $alerte->quartiers->modelKeys(),
            ])
            ->all());

        $maintenant = now();

        $alerte = collect($alertes)
            ->filter(fn (array $a): bool => in_array($habitant->quartier_id, $a['quartiers'], true)
                && Carbon::parse($a['debut'])->lte($maintenant) && Carbon::parse($a['fin'])->gt($maintenant))
            ->sortByDesc(fn (array $a): int => (int) array_search($a['niveau'], self::NIVEAU_OPTIONS, true))
            ->first();

        return $alerte === null ? null : ['niveau' => $alerte['niveau'], 'message' => $alerte['message']];
    }

    /**
     * À appeler aussi après un changement des quartiers (la table pivot ne déclenche pas d'évènement du modèle).
     */
    public static function oublierCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function concerne(?User $habitant): bool
    {
        return $habitant?->quartier_id !== null && $this->quartiers->contains('id', $habitant->quartier_id);
    }

    public function enCours(): bool
    {
        return $this->debut->lte(now()) && $this->fin->gt(now());
    }

    public function gravite(): int
    {
        return (int) array_search($this->niveau, self::NIVEAU_OPTIONS, true);
    }

    public function libelleNiveau(): string
    {
        return self::NIVEAU_LABELS[$this->niveau] ?? $this->niveau;
    }

    public function libelleQuartiers(): string
    {
        return $this->quartiers->pluck('nom')->sort()->implode(', ');
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
            'temperature_max' => 'integer',
        ];
    }
}
