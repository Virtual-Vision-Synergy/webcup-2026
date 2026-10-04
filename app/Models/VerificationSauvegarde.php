<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * F87 : résultat d'une vérification de sauvegarde de la base (historique affiché dans /admin/sauvegardes).
 * Écrit uniquement par App\Services\Sauvegardes ; user_id (qui a lancé la vérification) est assigné dans le code.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $fichier
 * @property int $taille
 * @property Carbon|null $sauvegarde_le
 * @property string $statut
 * @property int $nb_tables
 * @property int $nb_tables_base
 * @property string $rapport
 * @property array<string, mixed>|null $details
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
#[Fillable(['fichier', 'taille', 'sauvegarde_le', 'statut', 'nb_tables', 'nb_tables_base', 'rapport', 'details'])]
class VerificationSauvegarde extends Model
{
    public const COMPLETE = 'complete';

    public const INCOMPLETE = 'incomplete';

    public const ECHEC = 'echec';

    /** @var array<string, string> */
    public const STATUT_LABELS = [
        self::COMPLETE => 'Complète',
        self::INCOMPLETE => 'Incomplète',
        self::ECHEC => 'Échec',
    ];

    protected $table = 'verifications_sauvegarde';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sauvegarde_le' => 'datetime',
            'taille' => 'integer',
            'nb_tables' => 'integer',
            'nb_tables_base' => 'integer',
            'details' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function estComplete(): bool
    {
        return $this->statut === self::COMPLETE;
    }
}
