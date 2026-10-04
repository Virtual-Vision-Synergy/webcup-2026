<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * F85 : donnée incohérente repérée par le contrôle d'intégrité (App\Services\ControleIntegrite).
 *
 * Aucun champ n'est remplissable : écrite par le contrôle, résolue par un admin depuis Filament.
 *
 * @property int $id
 * @property string $signature
 * @property string $type
 * @property string $table_concernee
 * @property int|null $enregistrement_id
 * @property string $description
 * @property Carbon|null $detectee_le
 * @property Carbon|null $resolue_le
 * @property int|null $resolue_par
 * @property-read User|null $resolveur
 * @property string|null $resolution
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AnomalieDonnee extends Model
{
    protected $table = 'anomalies_donnees';

    public const TYPE_STATUT_IMPOSSIBLE = 'statut_impossible';

    public const TYPE_REFERENCE_ORPHELINE = 'reference_orpheline';

    public const TYPE_DATE_FUTURE = 'date_future';

    public const TYPE_DOUBLON = 'doublon';

    /** @var array<string, string> */
    public const TYPE_OPTIONS = [
        self::TYPE_STATUT_IMPOSSIBLE => 'Statut impossible',
        self::TYPE_REFERENCE_ORPHELINE => 'Référence orpheline',
        self::TYPE_DATE_FUTURE => 'Date dans le futur',
        self::TYPE_DOUBLON => 'Doublon',
    ];

    public const RESOLUTION_CORRIGEE = 'corrigee';

    public const RESOLUTION_IGNOREE = 'ignoree';

    public const RESOLUTION_DISPARUE = 'disparue';

    /** @var array<string, string> */
    public const RESOLUTION_OPTIONS = [
        self::RESOLUTION_CORRIGEE => 'Corrigée',
        self::RESOLUTION_IGNOREE => 'Ignorée (faux positif)',
        self::RESOLUTION_DISPARUE => 'Disparue d’elle-même',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enregistrement_id' => 'integer',
            'detectee_le' => 'datetime',
            'resolue_le' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolveur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolue_par');
    }

    public function estOuverte(): bool
    {
        return $this->resolue_le === null;
    }

    /**
     * @param  Builder<AnomalieDonnee>  $query
     */
    public function scopeOuvertes(Builder $query): void
    {
        $query->whereNull('resolue_le');
    }

    public function marquerResolue(string $resolution, ?User $par = null): void
    {
        $this->resolue_le = now();
        $this->resolue_par = $par?->id;
        $this->resolution = $resolution;
        $this->save();
    }
}
