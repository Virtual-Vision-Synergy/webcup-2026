<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Avis d'un habitant sur un projet de la ville ouvert à la consultation (F66). Ce n'est pas un vote officiel.
 * Un seul avis par habitant et par projet (contrainte unique en base), modifiable tant que la consultation est ouverte.
 *
 * user_id et projet_id ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 *
 * @property int $id
 * @property int $user_id
 * @property int $projet_id
 * @property string $position
 * @property string|null $commentaire
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Projet $projet
 */
#[Fillable(['position', 'commentaire'])]
class AvisProjet extends Model
{
    protected $table = 'avis_projets';

    public const POSITION_OPTIONS = ['pour', 'contre', 'sans_avis'];

    public const POSITION_LABELS = [
        'pour' => 'Pour',
        'contre' => 'Contre',
        'sans_avis' => 'Sans avis',
    ];

    /**
     * Valeur attendue par <x-tn.status-badge>.
     */
    public const POSITION_BADGES = [
        'pour' => 'normal',
        'contre' => 'alerte',
        'sans_avis' => 'info',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Projet, $this>
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(Projet::class);
    }

    public function positionLabel(): string
    {
        return self::POSITION_LABELS[$this->position] ?? $this->position;
    }

    public function positionBadge(): string
    {
        return self::POSITION_BADGES[$this->position] ?? 'info';
    }

    /**
     * « 04/10/2026 à 10:42 » (heure locale) : date de la dernière version de l'avis.
     */
    public function enregistreLe(): string
    {
        return Remontee::dateLocale($this->updated_at, 'd/m/Y à H:i');
    }
}
