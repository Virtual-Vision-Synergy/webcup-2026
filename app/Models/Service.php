<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * user_id n'est volontairement PAS remplissable : il est assigné dans le code.
 * Service municipal de Nova Terra (annuaire public).
 *
 * user_id et slug ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 * Le slug est généré à la création depuis le nom et ne change plus (URL stables).
 * mis_en_avant n'est pas remplissable non plus : réservé aux agents et admins (ServicePolicy::feature).
 */
#[Fillable(['nom', 'categorie', 'description', 'horaires', 'telephone', 'email', 'adresse', 'lieu_rendez_vous', 'pieces_a_fournir', 'duree_rendez_vous'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use Auditable, HasFactory;

    /** Catégories du catalogue (filtre et recherche). */
    public const CATEGORIE_OPTIONS = ['administratif', 'sante', 'social', 'education', 'culture', 'urbanisme', 'securite', 'economie'];

    /** Libellés affichés (avec accents). */
    public const CATEGORIE_LABELS = [
        'administratif' => 'Démarches administratives',
        'sante' => 'Santé',
        'social' => 'Social et solidarité',
        'education' => 'Éducation et jeunesse',
        'culture' => 'Culture, sport et loisirs',
        'urbanisme' => 'Urbanisme et cadre de vie',
        'securite' => 'Sécurité',
        'economie' => 'Commerce et marchés',
    ];

    /** Slugs qui entreraient en conflit avec les routes /services/... */
    private const RESERVED_SLUGS = ['create'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mis_en_avant' => 'boolean',
            'duree_rendez_vous' => 'integer',
        ];
    }

    /**
     * Services mis en avant d'abord, puis par nom.
     *
     * @param  Builder<Service>  $query
     */
    #[Scope]
    protected function prioritaires(Builder $query): void
    {
        $query->orderByDesc('mis_en_avant')->orderBy('nom');
    }

    protected static function booted(): void
    {
        static::creating(function (Service $service): void {
            if (blank($service->slug)) {
                $service->slug = self::uniqueSlug((string) $service->nom);
            }
        });
    }

    /**
     * Slug unique dérivé du nom : « etat-civil », puis « etat-civil-2 », « etat-civil-3 »…
     */
    public static function uniqueSlug(string $nom): string
    {
        $base = Str::slug($nom) ?: 'service';
        $slug = $base;
        $suffix = 2;

        while (in_array($slug, self::RESERVED_SLUGS, true) || self::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public static function labelCategorie(?string $categorie): ?string
    {
        return $categorie === null ? null : __(self::CATEGORIE_LABELS[$categorie] ?? ucfirst($categorie));
    }

    /**
     * Catégories dont le libellé contient le terme recherché, sans tenir compte des accents ni de la casse
     * (« sante » trouve « Santé »).
     *
     * @return array<int, string>
     */
    public static function categoriesCorrespondant(string $terme): array
    {
        $terme = Str::lower(Str::ascii(trim($terme)));

        if ($terme === '') {
            return [];
        }

        return array_keys(array_filter(
            self::CATEGORIE_LABELS,
            fn (string $label, string $cle): bool => str_contains(Str::lower(Str::ascii($label)), $terme) || str_contains($cle, $terme),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Services ouverts à la prise de rendez-vous (F39) : une durée de rendez-vous est renseignée.
     *
     * @param  Builder<Service>  $query
     */
    public function scopePrendRendezVous(Builder $query): void
    {
        $query->whereNotNull('duree_rendez_vous')->where('duree_rendez_vous', '>', 0);
    }

    /**
     * Lieu du rendez-vous : le guichet précis, sinon l'adresse du service.
     */
    public function lieuRendezVous(): ?string
    {
        return $this->lieu_rendez_vous ?: $this->adresse;
    }

    /**
     * Pièces à apporter, une par ligne dans la saisie.
     *
     * @return array<int, string>
     */
    public function piecesAFournir(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->pieces_a_fournir) ?: [])));
    }

    /**
     * @return HasMany<CreneauRendezVous, $this>
     */
    public function creneaux(): HasMany
    {
        return $this->hasMany(CreneauRendezVous::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
