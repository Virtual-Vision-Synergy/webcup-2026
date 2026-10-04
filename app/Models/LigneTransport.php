<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use Database\Factories\LigneTransportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ligne de transport municipal (bus, navette, taxi-be, train urbain).
 *
 * user_id, etat et perturbation ne sont volontairement PAS remplissables :
 * ils sont assignés dans le code (l'état du trafic est réservé aux agents et admins).
 * Les arrêts sont stockés un par ligne, dans l'ordre du parcours.
 * F97 : une interruption en cours déclarée dans Filament (relation interruptionsEnCours chargée) prime sur l'état saisi.
 */
#[Fillable(['numero', 'nom', 'mode', 'arrets', 'horaires', 'frequence'])]
class LigneTransport extends Model
{
    /** @use HasFactory<LigneTransportFactory> */
    use Auditable, HasAuditHistory, HasFactory;

    public const MODE_OPTIONS = ['bus', 'navette', 'taxi-be', 'train'];

    public const MODE_LABELS = [
        'bus' => 'Bus',
        'navette' => 'Navette',
        'taxi-be' => 'Taxi-be',
        'train' => 'Train urbain',
    ];

    public const ETAT_OPTIONS = ['normal', 'perturbe', 'interrompu'];

    public const ETAT_LABELS = [
        'normal' => 'Trafic normal',
        'perturbe' => 'Perturbé',
        'interrompu' => 'Interrompu',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<InterruptionTransport, $this>
     */
    public function interruptions(): BelongsToMany
    {
        return $this->belongsToMany(InterruptionTransport::class, 'interruption_transport_ligne');
    }

    /**
     * F97 : interruptions en cours (debut <= maintenant < fin) ; à charger avec with() avant l'affichage.
     *
     * @return BelongsToMany<InterruptionTransport, $this>
     */
    public function interruptionsEnCours(): BelongsToMany
    {
        return $this->interruptions()->where('debut', '<=', now())->where('fin', '>', now())->orderBy('debut');
    }

    /**
     * @return HasMany<AbonnementLigne, $this>
     */
    public function abonnements(): HasMany
    {
        return $this->hasMany(AbonnementLigne::class);
    }

    /**
     * Interruption en cours, si la relation a été chargée (sinon null : pas de requête paresseuse).
     */
    public function interruptionCourante(): ?InterruptionTransport
    {
        return $this->relationLoaded('interruptionsEnCours') ? $this->interruptionsEnCours->first() : null;
    }

    /**
     * État affiché : « interrompu » pendant une interruption déclarée (F97), sinon l'état saisi par l'agent.
     */
    public function etatAffiche(): string
    {
        return $this->interruptionCourante() !== null ? 'interrompu' : (string) $this->etat;
    }

    /**
     * Message affiché sous l'état : cause de l'interruption en cours, sinon message de perturbation.
     */
    public function messagePerturbation(): ?string
    {
        return $this->interruptionCourante()->cause ?? $this->perturbation;
    }

    /**
     * Arrêts dans l'ordre du parcours.
     *
     * @return array<int, string>
     */
    public function listeArrets(): array
    {
        return collect(preg_split('/\R/', (string) $this->arrets) ?: [])
            ->map(fn (string $arret) => trim($arret))
            ->filter()
            ->values()
            ->all();
    }

    public function estPerturbee(): bool
    {
        return $this->etatAffiche() !== 'normal';
    }

    public function modeLabel(): string
    {
        return __(self::MODE_LABELS[$this->mode] ?? ucfirst((string) $this->mode));
    }

    public function etatLabel(): string
    {
        return __(self::ETAT_LABELS[$this->etatAffiche()] ?? $this->etatAffiche());
    }

    /**
     * Valeur attendue par <x-tn.status-badge> (vert / ambre / magenta).
     */
    public function etatBadge(): string
    {
        return match ($this->etatAffiche()) {
            'perturbe' => 'perturbe',
            'interrompu' => 'alerte',
            default => 'normal',
        };
    }
}
