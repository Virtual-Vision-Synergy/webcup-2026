<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use App\Models\Concerns\HasCoordinates;
use Carbon\CarbonInterface;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * user_id n'est volontairement PAS remplissable : il est assigné dans le code.
 * Service municipal de Nova Terra (annuaire public).
 *
 * user_id et slug ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 * Le slug est généré à la création depuis le nom et ne change plus (URL stables).
 * mis_en_avant n'est pas remplissable non plus : réservé aux agents et admins (ServicePolicy::feature).
 * indisponible_depuis, motif_indisponibilite et retour_prevu_le ne sont pas remplissables :
 * réservés aux admins via rendreIndisponible() / retablir() (ServicePolicy::toggleAvailability, F63).
 * F64 : perturbe_depuis, alternative_texte, alternative_url et etat_mis_a_jour_le non plus : l'état complet
 * passe uniquement par mettreAJourEtat() (ServicePolicy::updateStatus : agent du service ou admin).
 *
 * @property CarbonInterface|null $indisponible_depuis
 * @property CarbonInterface|null $perturbe_depuis
 * @property CarbonInterface|null $retour_prevu_le
 * @property CarbonInterface|null $etat_mis_a_jour_le
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

    /** F64 : état actuel du service, déduit des dates (indisponible_depuis, perturbe_depuis). */
    public const ETAT_DISPONIBLE = 'disponible';

    public const ETAT_PERTURBE = 'perturbe';

    public const ETAT_INDISPONIBLE = 'indisponible';

    public const ETAT_OPTIONS = [self::ETAT_DISPONIBLE, self::ETAT_PERTURBE, self::ETAT_INDISPONIBLE];

    public const ETAT_LABELS = [
        self::ETAT_DISPONIBLE => 'Disponible',
        self::ETAT_PERTURBE => 'Perturbé',
        self::ETAT_INDISPONIBLE => 'Indisponible',
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
            'perturbe_depuis' => 'datetime',
            'etat_mis_a_jour_le' => 'datetime',
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
     * Services disponibles : ni désactivés par un administrateur (F63), ni en interruption en cours (F38).
     *
     * @param  Builder<Service>  $query
     */
    #[Scope]
    protected function disponibles(Builder $query): void
    {
        $query->whereNull('indisponible_depuis')->whereDoesntHave('interruptionCourante');
    }

    /**
     * F64 : services pleinement disponibles (ni perturbés ni indisponibles), pour le filtre du catalogue.
     *
     * @param  Builder<Service>  $query
     */
    #[Scope]
    protected function pleinementDisponibles(Builder $query): void
    {
        $query->disponibles()->whereNull('perturbe_depuis');
    }

    /**
     * Indisponible si un administrateur l'a désactivé (F63), si un agent l'a déclaré indisponible (F64)
     * ou si une interruption est en cours (F38).
     */
    public function estIndisponible(): bool
    {
        return $this->indisponible_depuis !== null || $this->interruptionEnCours() !== null;
    }

    public function estPerturbe(): bool
    {
        return ! $this->estIndisponible() && $this->perturbe_depuis !== null;
    }

    /**
     * F64 : disponible, perturbe ou indisponible.
     */
    public function etat(): string
    {
        return match (true) {
            $this->estIndisponible() => self::ETAT_INDISPONIBLE,
            $this->estPerturbe() => self::ETAT_PERTURBE,
            default => self::ETAT_DISPONIBLE,
        };
    }

    public function libelleEtat(): string
    {
        return self::ETAT_LABELS[$this->etat()];
    }

    /**
     * Libellé du badge : « Indisponible · Incident » quand une interruption F38 est en cours.
     */
    public function libelleEtatDetaille(): string
    {
        $interruption = $this->interruptionEnCours();

        return $this->libelleEtat().($interruption !== null ? ' · '.$interruption->libelleType() : '');
    }

    /**
     * Date de retour prévue dépassée : l'état ne change pas tout seul, on prévient l'habitant.
     */
    /*
    | F64 : informations d'état affichées aux habitants. Une interruption F38 en cours (déclarée dans l'espace
    | agent) est prioritaire ; sinon on lit les champs du service (F63 / F64).
    */

    public function motifEtat(): ?string
    {
        $interruption = $this->interruptionEnCours();

        return $interruption !== null ? $interruption->motif : $this->motif_indisponibilite;
    }

    public function retourPrevuEtat(): ?CarbonInterface
    {
        $interruption = $this->interruptionEnCours();

        return $interruption !== null ? $interruption->retour_prevu_at : $this->retour_prevu_le;
    }

    public function alternativeTexteEtat(): ?string
    {
        $interruption = $this->interruptionEnCours();

        return $interruption !== null ? $interruption->alternative : $this->alternative_texte;
    }

    /**
     * Lien de l'alternative : service de remplacement (F38) ou lien saisi par l'agent (F64).
     *
     * @return array{url: string, libelle: string, interne: bool}|null
     */
    public function alternativeLienEtat(): ?array
    {
        $remplacement = $this->interruptionEnCours()?->alternativeService;

        if ($remplacement !== null) {
            return ['url' => route('services.show', $remplacement), 'libelle' => $remplacement->nom, 'interne' => true];
        }

        return filled($this->alternative_url) ? ['url' => (string) $this->alternative_url, 'libelle' => 'Ouvrir l\'alternative', 'interne' => false] : null;
    }

    /**
     * Date de retour prévue dépassée : l'état ne change pas tout seul, on prévient l'habitant.
     */
    public function retourPrevuDepasse(): bool
    {
        $retour = $this->retourPrevuEtat();

        return $this->etat() !== self::ETAT_DISPONIBLE
            && $retour !== null
            && $retour->copy()->setTimezone(CreneauRendezVous::fuseau())->toDateString() < now(CreneauRendezVous::fuseau())->toDateString();
    }

    public function etatMisAJourLe(): ?CarbonInterface
    {
        $interruption = $this->interruptionEnCours();

        return $interruption !== null
            ? $interruption->updated_at ?? $interruption->debut_at
            : $this->etat_mis_a_jour_le ?? $this->indisponible_depuis ?? $this->perturbe_depuis;
    }

    /**
     * « Retour prévu le lundi 12 octobre 2026 » ou « Date de retour non connue » (heure de Nova Terra).
     */
    public function libelleRetourPrevu(): string
    {
        // F38 : formulation de l'interruption (avec l'heure, et la mention si la date est dépassée).
        $interruption = $this->interruptionEnCours();

        if ($interruption !== null) {
            return $interruption->libelleRetour();
        }

        $retour = $this->retourPrevuEtat();

        return $retour !== null
            ? 'Retour prévu le '.$retour->copy()->setTimezone(CreneauRendezVous::fuseau())->locale('fr')->translatedFormat('l j F Y')
            : 'Date de retour non connue';
    }

    /**
     * Message du refus d'une démarche ou d'un rendez-vous sur un service indisponible.
     */
    public function messageIndisponibilite(): string
    {
        $message = 'Ce service est actuellement indisponible : '
            .rtrim($this->motifEtat() ?: 'interruption en cours', '. ').'. '
            .$this->libelleRetourPrevu().'.';

        $alternative = $this->alternativeTexteEtat() ?: $this->alternativeLienEtat()['url'] ?? null;

        if (filled($alternative)) {
            $message .= ' Vous pouvez : '.rtrim((string) $alternative, '. ').'.';
        }

        return $message;
    }

    /**
     * F64 : change l'état du service (motif, retour prévu, alternative). Appeler après l'autorisation
     * (ServicePolicy::updateStatus). Modification au journal d'audit (Auditable) et au journal d'actions.
     */
    public function mettreAJourEtat(string $etat, ?string $motif = null, ?CarbonInterface $retourPrevuLe = null, ?string $alternativeTexte = null, ?string $alternativeUrl = null): void
    {
        $disponible = $etat === self::ETAT_DISPONIBLE;

        $this->indisponible_depuis = $etat === self::ETAT_INDISPONIBLE ? ($this->indisponible_depuis ?? now()) : null;
        $this->perturbe_depuis = $etat === self::ETAT_PERTURBE ? ($this->perturbe_depuis ?? now()) : null;
        $this->motif_indisponibilite = $disponible ? null : (trim((string) $motif) ?: null);
        $this->retour_prevu_le = $disponible ? null : $retourPrevuLe;
        $this->alternative_texte = $disponible ? null : (trim((string) $alternativeTexte) ?: null);
        $this->alternative_url = $disponible ? null : (trim((string) $alternativeUrl) ?: null);
        $this->etat_mis_a_jour_le = now();
        $this->save();

        // F38 : l'interruption en cours est close, sinon elle continuerait de rendre le service indisponible.
        $interruption = $this->interruptionEnCours();

        if ($interruption !== null) {
            $interruption->retabli_at = now();
            $interruption->retablissement()->associate(auth()->user());
            $interruption->save();
            $this->unsetRelation('interruptionCourante');
        }
    }

    /**
     * Désactive le service (panne, fermeture…) : il reste visible au catalogue mais n'accepte plus de démarche
     * ni de rendez-vous. Action tracée dans le journal.
     */
    public function rendreIndisponible(string $motif, ?Carbon $retourPrevuLe = null): void
    {
        $this->mettreAJourEtat(self::ETAT_INDISPONIBLE, $motif, $retourPrevuLe, $this->alternative_texte, $this->alternative_url);

        ActionLog::record('service_indisponible', $this);
    }

    /**
     * Remet le service en service. Action tracée dans le journal.
     */
    public function retablir(): void
    {
        $this->mettreAJourEtat(self::ETAT_DISPONIBLE);

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

    /** F71 : pictogramme de chaque catégorie, pour se repérer sans lire (icônes Heroicons de Flux). */
    public const CATEGORIE_ICONES = [
        'administratif' => 'document-text',
        'sante' => 'heart',
        'social' => 'user-group',
        'education' => 'academic-cap',
        'culture' => 'musical-note',
        'urbanisme' => 'building-office-2',
        'securite' => 'shield-check',
        'economie' => 'banknotes',
    ];

    public static function iconeCategorie(?string $categorie): string
    {
        return self::CATEGORIE_ICONES[$categorie] ?? 'landmark';
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
            throw ValidationException::withMessages([$champ => $this->messageIndisponibilite()]);
        }
    }

    /**
     * F76 : avis des habitants sur ce service (masqués compris : filtrer avec ->visibles()).
     *
     * @return HasMany<ServiceReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ServiceReview::class);
    }

    /**
     * F76 : note moyenne (une décimale), nombre d'avis et répartition 5 → 1, sur les seuls avis publiés.
     * Une seule requête groupée.
     *
     * @return array{moyenne: float|null, total: int, repartition: array<int, int>}
     */
    public function statistiquesAvis(): array
    {
        $parNote = $this->reviews()->visibles()
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $repartition = [];
        foreach ([5, 4, 3, 2, 1] as $note) {
            $repartition[$note] = (int) ($parNote[$note] ?? 0);
        }

        $total = array_sum($repartition);
        $somme = array_sum(array_map(fn (int $note, int $nombre): int => $note * $nombre, array_keys($repartition), $repartition));

        return [
            'moyenne' => $total > 0 ? round($somme / $total, 1) : null,
            'total' => $total,
            'repartition' => $repartition,
        ];
    }

    /**
     * F70 : agents rattachés à ce service.
     *
     * @return BelongsToMany<User, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
