<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * user_id n'est volontairement PAS remplissable : il est assigné dans le code.
 * Service municipal de Nova Terra (annuaire public).
 *
 * user_id et slug ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 * Le slug est généré à la création depuis le nom et ne change plus (URL stables).
 */
#[Fillable(['nom', 'categorie', 'description', 'horaires', 'telephone', 'email', 'adresse'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

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
        return $categorie === null ? null : (self::CATEGORIE_LABELS[$categorie] ?? ucfirst($categorie));
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
