<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Notifications\Avis;
use Carbon\CarbonInterface;
use Database\Factories\PartnerOfferingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service proposé par un partenaire (F99), affiché dans le catalogue avec les services municipaux (F32).
 *
 * partner_id, created_by et slug ne sont volontairement PAS remplissables : partner_id vient du compte partenaire
 * connecté (ou du choix validé d'un admin), created_by de l'utilisateur connecté, le slug est généré à la création.
 *
 * opening_hours : même format que Partner::opening_hours (F74) ; vide = horaires du partenaire.
 *
 * @property int $id
 * @property int $partner_id
 * @property int|null $created_by
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property string|null $conditions
 * @property array<string, array<int, array{start: string, end: string}>>|null $opening_hours
 * @property string $status
 * @property Carbon|null $unavailable_until
 * @property string|null $booking_url
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property string|null $alternative_text
 * @property string|null $alternative_url
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Partner $partner
 */
#[Fillable(['title', 'description', 'conditions', 'opening_hours', 'status', 'unavailable_until', 'booking_url', 'contact_phone', 'contact_email', 'alternative_text', 'alternative_url', 'is_published'])]
class PartnerOffering extends Model
{
    /** @use HasFactory<PartnerOfferingFactory> */
    use Auditable, HasFactory;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_FULL = 'full';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_OPTIONS = [self::STATUS_AVAILABLE, self::STATUS_FULL, self::STATUS_UNAVAILABLE];

    public const STATUS_LABELS = [
        self::STATUS_AVAILABLE => 'Disponible',
        self::STATUS_FULL => 'Complet',
        self::STATUS_UNAVAILABLE => 'Indisponible',
    ];

    /** Icône de chaque état (F43 : jamais la couleur seule). */
    public const STATUS_ICONES = [
        self::STATUS_AVAILABLE => 'check-circle',
        self::STATUS_FULL => 'user-group',
        self::STATUS_UNAVAILABLE => 'x-circle',
    ];

    /** Filtre « Services municipaux / Services partenaires / Tous » du catalogue. */
    public const TYPE_CATALOGUE_OPTIONS = [
        'tous' => 'Tous les services',
        'municipaux' => 'Services municipaux',
        'partenaires' => 'Services partenaires',
    ];

    /** Slugs qui entreraient en conflit avec des routes. */
    private const RESERVED_SLUGS = ['create', 'me-prevenir'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'unavailable_until' => 'date',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PartnerOffering $offering): void {
            if (blank($offering->slug)) {
                $offering->slug = self::uniqueSlug((string) $offering->title);
            }
        });

        // Retour à « Disponible » (formulaire, changement rapide, admin) : les abonnés sont prévenus une seule fois.
        static::updated(function (PartnerOffering $offering): void {
            if ($offering->wasChanged('status') && $offering->status === self::STATUS_AVAILABLE) {
                $offering->prevenirAbonnes();
            }
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'service-partenaire';
        $slug = $base;
        $suffix = 2;

        while (in_array($slug, self::RESERVED_SLUGS, true) || self::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Visible du public : service publié d'un partenaire publié.
     *
     * @param  Builder<PartnerOffering>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true)->whereHas('partner', fn (Builder $partner) => $partner->where('is_published', true));
    }

    /**
     * Liste de gestion : tout pour l'admin, uniquement les services de SON partenaire pour un compte partenaire.
     *
     * @param  Builder<PartnerOffering>  $query
     */
    #[Scope]
    protected function gerablesPar(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            $query->where('partner_id', $user->isPartenaire() ? (int) $user->partner_id : 0);
        }
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    /**
     * Horaires propres au service s'il en a, sinon ceux du partenaire (F74). Renvoie un Partner pour réutiliser
     * hoursLabel() et openingStatus() sans dupliquer le calcul.
     */
    public function horaires(): Partner
    {
        if ($this->aDesHorairesPropres()) {
            return (new Partner)->forceFill(['opening_hours' => $this->opening_hours]);
        }

        return $this->partner;
    }

    public function aDesHorairesPropres(): bool
    {
        foreach ((array) $this->opening_hours as $plages) {
            if ($plages !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{open: bool, label: string, color: string, icon: string}
     */
    public function openingStatus(?CarbonInterface $now = null): array
    {
        return $this->horaires()->openingStatus($now);
    }

    /**
     * Filtre « Disponible maintenant » : état Disponible ET ouvert à l'instant (heure de Nova Terra).
     */
    public function estDisponibleMaintenant(?CarbonInterface $now = null): bool
    {
        return $this->isAvailable() && $this->openingStatus($now)['open'];
    }

    public function libelleStatut(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'Indisponible';
    }

    /**
     * « Disponible », « Complet » ou « Indisponible jusqu'au lundi 12 octobre 2026 ».
     */
    public function libelleEtatDetaille(): string
    {
        $jusquAu = $this->unavailable_until;

        if ($this->status === self::STATUS_UNAVAILABLE && $jusquAu instanceof CarbonInterface) {
            return __('Indisponible jusqu\'au :date', ['date' => $jusquAu->settings(['locale' => 'fr'])->translatedFormat('l j F Y')]);
        }

        return __($this->libelleStatut());
    }

    /**
     * État au sens du badge F43 (<x-tn.status-badge>) : normal, perturbe (complet) ou alerte (indisponible).
     */
    public function etatBadge(): string
    {
        return match ($this->status) {
            self::STATUS_AVAILABLE => 'normal',
            self::STATUS_FULL => 'perturbe',
            default => 'alerte',
        };
    }

    public function iconeStatut(): string
    {
        return self::STATUS_ICONES[$this->status] ?? 'x-circle';
    }

    /**
     * Prochaine action possible, calculée côté serveur : un bouton principal et, si renseignée, une alternative.
     *
     * @return array{principale: array{type: string, label: string, icon: string, url: string|null, externe: bool}, alternative: array{label: string, url: string|null, texte: string|null}|null}
     */
    public function prochaineAction(): array
    {
        if (! $this->isAvailable()) {
            $principale = ['type' => 'prevenir', 'label' => 'Me prévenir quand disponible', 'icon' => 'bell-alert', 'url' => null, 'externe' => false];
        } elseif ($this->booking_url) {
            $principale = ['type' => 'reserver', 'label' => 'Réserver', 'icon' => 'calendar-days', 'url' => $this->booking_url, 'externe' => true];
        } else {
            $contact = $this->contactLink();
            $principale = ['type' => 'contacter', 'label' => 'Contacter', 'icon' => str_starts_with((string) $contact, 'mailto:') ? 'envelope' : 'phone', 'url' => $contact, 'externe' => false];
        }

        $alternative = $this->alternative_url || $this->alternative_text
            ? ['label' => 'Voir une alternative', 'url' => $this->alternative_url, 'texte' => $this->alternative_text]
            : null;

        return ['principale' => $principale, 'alternative' => $alternative];
    }

    /**
     * tel: du service, sinon mailto: du service, sinon téléphone du partenaire.
     */
    public function contactLink(): ?string
    {
        if ($this->contact_phone) {
            return 'tel:'.preg_replace('/[^0-9+]/', '', $this->contact_phone);
        }

        if ($this->contact_email) {
            return 'mailto:'.$this->contact_email;
        }

        return $this->partner->phone ? $this->partner->telLink() : null;
    }

    /**
     * Abonnés « Me prévenir » pas encore prévenus : notification « Avis » (F30), puis notified_at renseigné.
     */
    public function prevenirAbonnes(): void
    {
        $abonnements = $this->subscriptions()->whereNull('notified_at')->with('user')->get();

        if ($abonnements->isEmpty()) {
            return;
        }

        $avis = new Avis(
            __('« :titre » est de nouveau disponible', ['titre' => $this->title]),
            [__('Le service « :titre » proposé par :partenaire est de nouveau disponible.', ['titre' => $this->title, 'partenaire' => $this->partner->name])],
            __('Voir le service'),
            route('catalogue.partners.show', $this),
        );

        $this->subscriptions()->whereKey($abonnements->modelKeys())->update(['notified_at' => now()]);

        DB::afterCommit(function () use ($abonnements, $avis): void {
            foreach ($abonnements as $abonnement) {
                $abonne = $abonnement->user;

                $abonne->notifyNow($avis, ['database']);

                if ($abonne->aUnEmail()) {
                    try {
                        $abonne->notifyNow($avis, ['mail']);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AvailabilitySubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(AvailabilitySubscription::class);
    }
}
