<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Requêtes du journal d'audit (F47) : lecture seule, toute mise à jour ou suppression de masse est refusée.
 *
 * @extends Builder<AuditLog>
 */
class AuditLogBuilder extends Builder
{
    /**
     * @param  array<mixed>  $values
     */
    public function update(array $values): int
    {
        throw new LogicException('Les entrées du journal d’audit ne peuvent pas être modifiées.');
    }

    public function delete(): mixed
    {
        throw new LogicException('Les entrées du journal d’audit ne peuvent pas être supprimées.');
    }

    public function forceDelete(): mixed
    {
        throw new LogicException('Les entrées du journal d’audit ne peuvent pas être supprimées.');
    }
}
