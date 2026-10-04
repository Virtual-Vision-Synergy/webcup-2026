<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use Database\Factories\LigneTransportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de transport municipal (bus, navette, taxi-be, train urbain).
 *
 * user_id, etat et perturbation ne sont volontairement PAS remplissables :
 * ils sont assignés dans le code (l'état du trafic est réservé aux agents et admins).
 * Les arrêts sont stockés un par ligne, dans l'ordre du parcours.
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
        return $this->etat !== 'normal';
    }

    public function modeLabel(): string
    {
        return __(self::MODE_LABELS[$this->mode] ?? ucfirst((string) $this->mode));
    }

    public function etatLabel(): string
    {
        return __(self::ETAT_LABELS[$this->etat] ?? (string) $this->etat);
    }

    /**
     * Valeur attendue par <x-tn.status-badge> (vert / ambre / magenta).
     */
    public function etatBadge(): string
    {
        return match ($this->etat) {
            'perturbe' => 'perturbe',
            'interrompu' => 'alerte',
            default => 'normal',
        };
    }
}
