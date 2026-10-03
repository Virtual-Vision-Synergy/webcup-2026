<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Journal d'audit (F47) : consultable par les agents et les administrateurs, jamais modifiable ni supprimable,
 * et aucune entrée ne se crée depuis l'interface (seul App\Services\AuditLogger écrit).
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAgent() || $user->isAdmin();
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->isAgent() || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function restore(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function forceDelete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
