<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rôle d'un compte. Les 3 rôles de base sont créés par la migration create_roles_table.
 *
 * @property int $id
 * @property string $code
 * @property string $label
 */
#[Fillable(['code', 'label'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    public const CITOYEN = 'citoyen';

    public const AGENT = 'agent';

    public const ADMIN = 'admin';

    /** Rôles de base, non supprimables. */
    public const CODES = [self::CITOYEN, self::AGENT, self::ADMIN];

    /** @var array<string, int> */
    private static array $ids = [];

    /**
     * Id d'un rôle à partir de son code (mis en cache pour la durée de la requête).
     */
    public static function idFor(string $code): int
    {
        return self::$ids[$code] ??= (int) self::query()->where('code', $code)->value('id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
