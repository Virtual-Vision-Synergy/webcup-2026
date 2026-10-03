<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
    use Auditable, HasAuditHistory, HasFactory;

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
     * Interruptions du service, de la plus récente à la plus ancienne (F38, historique conservé).
     *
     * @return HasMany<ServiceInterruption, $this>
     */
    public function interruptions(): HasMany
    {
        return $this->hasMany(ServiceInterruption::class)->latest('debut_at')->latest('id');
    }

    /**
     * Interruption en cours (commencée, non rétablie). Relation pour le chargement groupé du catalogue :
     * Service::with('interruptionCourante') évite une requête par carte.
     *
     * @return HasOne<ServiceInterruption, $this>
     */
    public function interruptionCourante(): HasOne
    {
        return $this->hasOne(ServiceInterruption::class)->enCours()->latest('debut_at')->latest('id');
    }

    public function interruptionEnCours(): ?ServiceInterruption
    {
        return $this->interruptionCourante;
    }

    public function estIndisponible(): bool
    {
        return $this->interruptionEnCours() !== null;
    }

    /**
     * Services sans interruption en cours.
     *
     * @param  Builder<Service>  $query
     */
    #[Scope]
    protected function disponibles(Builder $query): void
    {
        $query->whereDoesntHave('interruptionCourante');
    }

    /**
     * Seul point de contrôle côté serveur avant de démarrer une démarche sur ce service (F38).
     * Lève une erreur de validation en français (jamais de 500) si le service est interrompu.
     *
     * @throws ValidationException
     */
    public function assertDisponible(string $champ = 'service'): void
    {
        // Relecture depuis la base : le statut a pu changer depuis le chargement de la page.
        $this->unsetRelation('interruptionCourante');

        if ($this->estIndisponible()) {
            throw ValidationException::withMessages([$champ => ServiceInterruption::MESSAGE_DEMARCHE_SUSPENDUE]);
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
