<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use App\Models\Concerns\HasCoordinates;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * user_id n'est volontairement PAS remplissable : il est assigné dans le code.
 * Service municipal de Nova Terra (annuaire public).
 *
 * user_id et slug ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 * Le slug est généré à la création depuis le nom et ne change plus (URL stables).
 * mis_en_avant n'est pas remplissable non plus : réservé aux agents et admins (ServicePolicy::feature).
 * indisponible_depuis, motif_indisponibilite et retour_prevu_le ne sont pas remplissables :
 * réservés aux admins via rendreIndisponible() / retablir() (ServicePolicy::toggleAvailability, F63).
 */
#[Fillable(['nom', 'categorie', 'description', 'horaires', 'telephone', 'email', 'adresse', 'lieu_rendez_vous', 'pieces_a_fournir', 'duree_rendez_vous', 'latitude', 'longitude'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use Auditable, HasAuditHistory, HasCoordinates, HasFactory;

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

    /**
     * Numéros d'urgence affichés en tête de la page Urgences / Santé (F46), gratuits et joignables 24 h/24.
     *
     * @var array<int, array{numero: string, label: string, detail: string, icon: string}>
     */
    public const NUMEROS_URGENCE = [
        ['numero' => '117', 'label' => 'Police secours', 'detail' => 'Agression, accident, danger immédiat', 'icon' => 'shield-exclamation'],
        ['numero' => '118', 'label' => 'Sapeurs-pompiers', 'detail' => 'Incendie, inondation, secours à personne', 'icon' => 'fire'],
        ['numero' => '124', 'label' => 'Urgences médicales (SAMU)', 'detail' => 'Malaise, blessure grave, accouchement', 'icon' => 'heart'],
        ['numero' => '+261 20 22 401 17', 'label' => 'Police municipale', 'detail' => 'Patrouilles 24 h/24', 'icon' => 'phone'],
    ];

    /**
     * Hôpitaux et services d'urgence de la ville (F46), ajoutés à l'annuaire en catégorie santé
     * (migration de données et ServiceSeeder).
     *
     * @var array<int, array{nom: string, description: string, horaires: string, telephone: string, email: string|null, adresse: string, latitude: float, longitude: float}>
     */
    public const ETABLISSEMENTS_SANTE = [
        [
            'nom' => 'Centre hospitalier de Nova Terra',
            'description' => "Hôpital principal de la ville : service d'urgences adultes ouvert jour et nuit, chirurgie, radiologie et laboratoire.\nEn cas d'urgence vitale, appelez d'abord le 124.",
            'horaires' => "Urgences : 24 h/24, 7 j/7\nConsultations : lundi au vendredi, 8 h 00 – 16 h 00",
            'telephone' => '+261 20 22 410 00',
            'email' => 'accueil@chu-novaterra.mg',
            'adresse' => "Avenue de l'Hôpital, quartier Ampefiloha, Nova Terra",
            'latitude' => -18.9152,
            'longitude' => 47.5203,
        ],
        [
            'nom' => 'Hôpital mère-enfant Ravaka',
            'description' => 'Maternité, urgences pédiatriques et gynécologiques, suivi de grossesse et néonatologie.',
            'horaires' => "Urgences pédiatriques et maternité : 24 h/24, 7 j/7\nConsultations : lundi au samedi, 8 h 00 – 12 h 00",
            'telephone' => '+261 20 22 410 50',
            'email' => 'contact@hopital-ravaka.mg',
            'adresse' => '22 rue des Flamboyants, quartier Isoraka, Nova Terra',
            'latitude' => -18.9034,
            'longitude' => 47.5327,
        ],
        [
            'nom' => 'Clinique Fanantenana',
            'description' => 'Clinique de proximité : petites urgences (plaies, fractures simples, fièvre), consultations sans rendez-vous et soins infirmiers.',
            'horaires' => "Urgences : tous les jours, 7 h 00 – 22 h 00\nLa nuit : Centre hospitalier de Nova Terra",
            'telephone' => '+261 20 22 410 80',
            'email' => null,
            'adresse' => '5 rue Rainandriamampandry, quartier Ankadifotsy, Nova Terra',
            'latitude' => -18.9226,
            'longitude' => 47.5251,
        ],
        [
            'nom' => 'Pharmacie de garde municipale',
            'description' => "Délivrance de médicaments les nuits, dimanches et jours fériés, sur présentation d'une ordonnance. Liste des pharmacies de garde de la semaine affichée sur place.",
            'horaires' => "Lundi au samedi : 19 h 00 – 8 h 00\nDimanche et jours fériés : 24 h/24",
            'telephone' => '+261 20 22 410 99',
            'email' => null,
            'adresse' => "Place de l'Indépendance, à côté de l'hôtel de ville, Nova Terra",
            'latitude' => -18.9117,
            'longitude' => 47.5269,
        ],
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
            'indisponible_depuis' => 'datetime',
            'retour_prevu_le' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
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

    /**
     * Services disponibles : ceux qu'un administrateur n'a pas désactivés (F63).
     *
     * @param  Builder<Service>  $query
     */
    #[Scope]
    protected function disponibles(Builder $query): void
    {
        $query->whereNull('indisponible_depuis');
    }

    public function estIndisponible(): bool
    {
        return $this->indisponible_depuis !== null;
    }

    /**
     * Désactive le service (panne, fermeture…) : il reste visible au catalogue mais n'accepte plus de démarche
     * ni de rendez-vous. Action tracée dans le journal.
     */
    public function rendreIndisponible(string $motif, ?Carbon $retourPrevuLe = null): void
    {
        $this->indisponible_depuis = now();
        $this->motif_indisponibilite = trim($motif);
        $this->retour_prevu_le = $retourPrevuLe;
        $this->save();

        ActionLog::record('service_indisponible', $this);
    }

    /**
     * Remet le service en service. Action tracée dans le journal.
     */
    public function retablir(): void
    {
        $this->indisponible_depuis = null;
        $this->motif_indisponibilite = null;
        $this->retour_prevu_le = null;
        $this->save();

        ActionLog::record('service_retabli', $this);
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
