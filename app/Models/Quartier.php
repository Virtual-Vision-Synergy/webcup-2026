<?php

namespace App\Models;

use Database\Factories\QuartierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Quartier de Nova Terra (F29) : sert à cibler les alertes et à situer l'habitant.
 * Les quartiers de base sont créés par la migration create_quartiers_table.
 *
 * @property int $id
 * @property string $nom
 * @property string $slug
 */
#[Fillable(['nom', 'slug'])]
class Quartier extends Model
{
    /** @use HasFactory<QuartierFactory> */
    use HasFactory;

    /** Quartiers de base (nom => slug). */
    public const DE_BASE = ['Nord' => 'nord', 'Sud' => 'sud', 'Est' => 'est', 'Ouest' => 'ouest', 'Centre' => 'centre'];

    /**
     * @return HasMany<User, $this>
     */
    public function habitants(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Annonce, $this>
     */
    public function annonces(): HasMany
    {
        return $this->hasMany(Annonce::class);
    }

    public static function idPour(string $slug): ?int
    {
        $id = self::query()->where('slug', $slug)->value('id');

        return $id === null ? null : (int) $id;
    }
}
