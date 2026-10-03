<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RendezVousFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rendez-vous d'un habitant avec un agent (F39).
 *
 * Seul le motif saisi par l'habitant est remplissable : user_id, service_id, creneau_id, statut et annule_le
 * sont assignés dans le code (PriseDeRendezVous, changerStatut).
 *
 * @property int $id
 * @property int $user_id
 * @property int $service_id
 * @property int $creneau_id
 * @property string|null $motif
 * @property string $statut
 * @property CarbonImmutable|null $annule_le
 * @property CarbonImmutable|null $created_at
 * @property-read User $user
 * @property-read Service $service
 * @property-read CreneauRendezVous $creneau
 */
#[Fillable(['motif'])]
class RendezVous extends Model
{
    /** @use HasFactory<RendezVousFactory> */
    use HasFactory;

    protected $table = 'rendez_vous';

    public const STATUT_OPTIONS = ['confirme', 'annule', 'honore', 'absent'];

    /** Statuts qu'un agent peut poser après le rendez-vous. */
    public const STATUTS_AGENT = ['honore', 'absent'];

    public const STATUT_LABELS = [
        'confirme' => 'Confirmé',
        'annule' => 'Annulé',
        'honore' => 'Honoré',
        'absent' => 'Absent',
    ];

    /** Couleurs Flux des badges. */
    public const STATUT_COLORS = ['confirme' => 'green', 'annule' => 'zinc', 'honore' => 'blue', 'absent' => 'red'];

    /**
     * Valeur par défaut du statut : la première de STATUT_OPTIONS.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['statut' => self::STATUT_OPTIONS[0]];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'annule_le' => 'datetime',
        ];
    }

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
     * @return BelongsTo<CreneauRendezVous, $this>
     */
    public function creneau(): BelongsTo
    {
        return $this->belongsTo(CreneauRendezVous::class, 'creneau_id');
    }

    /**
     * Rendez-vous dont le créneau commence dans l'intervalle [debut, fin[ (bornes UTC).
     *
     * @param  Builder<RendezVous>  $query
     */
    public function scopeEntre(Builder $query, CarbonImmutable $debut, CarbonImmutable $fin): void
    {
        $query->whereHas('creneau', fn (Builder $q) => $q->where('debut', '>=', $debut)->where('debut', '<', $fin));
    }

    public static function libelleStatut(string $statut): string
    {
        return self::STATUT_LABELS[$statut] ?? ucfirst(str_replace('_', ' ', $statut));
    }

    public function couleurStatut(): string
    {
        return self::STATUT_COLORS[$this->statut] ?? 'zinc';
    }

    /**
     * État du badge <x-tn.status-badge> (vert = confirmé, magenta = absent, cyan sinon).
     */
    public function etatStatut(): string
    {
        return match ($this->statut) {
            'confirme' => 'normal',
            'absent' => 'alerte',
            default => 'info',
        };
    }

    public function estConfirme(): bool
    {
        return $this->statut === 'confirme';
    }

    public function estAVenir(): bool
    {
        return $this->creneau->debut->isFuture();
    }

    /**
     * Confirmé, et le début est encore au-delà du délai minimum d'annulation.
     */
    public function estAnnulable(): bool
    {
        return $this->estConfirme()
            && $this->creneau->debut->greaterThan(now()->addMinutes((int) config('rendez_vous.delai_annulation_minutes', 0)));
    }

    /**
     * Seul point de passage pour un statut posé par un agent (policy changerStatut).
     */
    public function changerStatut(string $statut): void
    {
        if (! in_array($statut, self::STATUTS_AGENT, true)) {
            throw new \InvalidArgumentException("Statut inconnu : {$statut}");
        }

        $this->statut = $statut;
        $this->save();
    }
}
