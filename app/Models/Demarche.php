<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use App\Models\Concerns\PrevientDuChangementDeStatut;
use Database\Factories\DemarcheFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Démarche administrative déposée par un habitant auprès d'un service municipal.
 * Visible uniquement par son auteur et par le personnel (agents, admins) : voir DemarchePolicy.
 *
 * user_id et statut ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 */
#[Fillable(['titre', 'description', 'service_id'])]
class Demarche extends Model
{
    /** @use HasFactory<DemarcheFactory> */
    use Auditable, HasAuditHistory, HasFactory, PrevientDuChangementDeStatut;

    public const STATUT_OPTIONS = ['deposee', 'en_cours', 'traitee', 'refusee'];

    /** Couleurs Flux des badges. */
    public const STATUT_COLORS = ['deposee' => 'amber', 'en_cours' => 'blue', 'traitee' => 'green', 'refusee' => 'red'];

    /** État affiché par le badge Terra Nova (la couleur indique un état : normal, perturbé, alerte, info). */
    public const STATUT_ETATS = ['deposee' => 'perturbe', 'en_cours' => 'info', 'traitee' => 'normal', 'refusee' => 'alerte'];

    /** Libellés affichés (avec accents). */
    public const STATUT_LABELS = ['deposee' => 'Déposée', 'en_cours' => 'En cours', 'traitee' => 'Traitée', 'refusee' => 'Refusée'];

    /** États « en attente de prise en charge » : aucun agent ne s'en occupe encore (D17). */
    public const STATUTS_EN_ATTENTE_PRISE_EN_CHARGE = ['deposee'];

    /**
     * Valeur par défaut du statut : la première de STATUT_OPTIONS.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['statut' => self::STATUT_OPTIONS[0]];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Demandes qui attendent encore une prise en charge par un agent.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAwaitingHandling(Builder $query): void
    {
        $query->whereIn('statut', self::STATUTS_EN_ATTENTE_PRISE_EN_CHARGE);
    }

    public static function libelleStatut(string $statut): string
    {
        return __(self::STATUT_LABELS[$statut] ?? ucfirst(str_replace('_', ' ', $statut)));
    }

    public function couleurStatut(): string
    {
        return self::STATUT_COLORS[$this->statut] ?? 'zinc';
    }

    public function etatStatut(): string
    {
        return self::STATUT_ETATS[$this->statut] ?? 'info';
    }

    /**
     * Seul point de passage pour modifier le statut (réservé aux agents et admins : policy changerStatut).
     * Si l'état change vraiment, le propriétaire est prévenu (F49).
     */
    public function changerStatut(string $statut): void
    {
        if (! in_array($statut, self::STATUT_OPTIONS, true)) {
            throw new \InvalidArgumentException("Statut inconnu : {$statut}");
        }

        $statutAvant = (string) $this->statut;

        $this->statut = $statut;
        $this->save();

        $this->prevenirProprietaire($statutAvant);
    }
}
