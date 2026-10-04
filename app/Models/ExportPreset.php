<?php

namespace App\Models;

use Database\Factories\ExportPresetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F88 : préréglage d'export des demandes, propre à son auteur (ExportPresetPolicy).
 *
 * Seul le nom est remplissable : user_id est assigné dans le code ; filters, columns et format
 * sont nettoyés par App\Services\ExportDemarches (liste blanche) puis affectés côté serveur.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property array<string, string|bool> $filters
 * @property list<string> $columns
 * @property string $format
 */
#[Fillable(['name'])]
class ExportPreset extends Model
{
    /** @use HasFactory<ExportPresetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'columns' => 'array',
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
     * @param  Builder<self>  $query
     */
    public function scopeDe(Builder $query, User $user): void
    {
        $query->whereBelongsTo($user);
    }
}
