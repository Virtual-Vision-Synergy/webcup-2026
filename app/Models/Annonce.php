<?php

namespace App\Models;

use Database\Factories\AnnonceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Message général diffusé en bandeau à tous les habitants (D18) pendant sa période de validité.
 * Les dates sont stockées en UTC ; les agents les saisissent en heure de Madagascar (FUSEAU).
 *
 * @property int $id
 * @property int $user_id
 * @property string $titre
 * @property string $contenu
 * @property string $niveau
 * @property Carbon $debut
 * @property Carbon $fin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * user_id (l'auteur) n'est volontairement PAS remplissable : il est assigné dans le code.
 */
#[Fillable(['titre', 'contenu', 'niveau', 'debut', 'fin'])]
class Annonce extends Model
{
    /** @use HasFactory<AnnonceFactory> */
    use HasFactory;

    /** Du moins au plus grave. */
    public const NIVEAU_OPTIONS = ['information', 'important', 'urgent'];

    /** Libellé texte affiché avec la couleur (l'information ne repose jamais sur la seule couleur). */
    public const NIVEAU_LIBELLES = [
        'information' => 'Information',
        'important' => 'Important',
        'urgent' => 'Urgent',
    ];

    /** Fuseau de saisie et d'affichage pour les agents (l'application stocke en UTC). */
    public const FUSEAU = 'Indian/Antananarivo';

    public const CACHE_KEY = 'annonces.en-diffusion';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Messages en cours de diffusion : debut <= maintenant < fin.
     *
     * @param  Builder<Annonce>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('debut', '<=', now())->where('fin', '>', now());
    }

    /**
     * Messages à afficher dans le bandeau, du plus grave au moins grave puis du plus récent au plus ancien.
     *
     * Le cache (court, invalidé à chaque création, modification ou suppression) contient les messages non expirés ;
     * le filtre sur les dates est refait à chaque affichage pour qu'un message programmé apparaisse pile à l'heure.
     * On met en cache des tableaux bruts, pas des objets : config/cache.php interdit de désérialiser des classes
     * (serializable_classes = false), les modèles sont donc reconstruits avec hydrate().
     *
     * @return Collection<int, Annonce>
     */
    public static function enDiffusion(): Collection
    {
        /** @var list<array<string, mixed>> $lignes */
        $lignes = Cache::remember(self::CACHE_KEY, 60, fn (): array => self::query()
            ->where('fin', '>', now())
            ->get(['id', 'titre', 'contenu', 'niveau', 'debut', 'fin', 'updated_at'])
            ->map(fn (Annonce $annonce): array => $annonce->getAttributes())
            ->all());

        $maintenant = now();

        return self::hydrate($lignes)
            ->filter(fn (Annonce $annonce): bool => $annonce->debut->lte($maintenant) && $annonce->fin->gt($maintenant))
            ->sortBy([
                fn (Annonce $a, Annonce $b): int => $b->gravite() <=> $a->gravite(),
                fn (Annonce $a, Annonce $b): int => $b->debut <=> $a->debut,
            ])
            ->values();
    }

    public function gravite(): int
    {
        return (int) array_search($this->niveau, self::NIVEAU_OPTIONS, true);
    }

    public function libelleNiveau(): string
    {
        return self::NIVEAU_LIBELLES[$this->niveau] ?? ucfirst($this->niveau);
    }

    /**
     * @return 'programme'|'en_cours'|'expire'
     */
    public function statut(): string
    {
        return match (true) {
            $this->debut->isFuture() => 'programme',
            $this->fin->lte(now()) => 'expire',
            default => 'en_cours',
        };
    }

    /**
     * Clé de fermeture côté navigateur : un message modifié réapparaît.
     */
    public function cleFermeture(): string
    {
        return 'tn.annonce.'.$this->id.'.'.($this->updated_at->timestamp ?? 0);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'debut' => 'datetime',
            'fin' => 'datetime',
        ];
    }
}
