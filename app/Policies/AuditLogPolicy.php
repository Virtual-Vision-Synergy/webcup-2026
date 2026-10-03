<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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

    /**
     * F70 : un agent n'ouvre pas une entrée portant sur un dossier d'un service qu'il ne couvre pas.
     */
    public function view(User $user, AuditLog $auditLog): Response
    {
        if ($user->isAdmin()) {
            return Response::allow();
        }

        if (! $user->isAgent()) {
            return Response::deny();
        }

        return AuditLog::query()->visibleTo($user)->whereKey($auditLog->id)->exists()
            ? Response::allow()
            : Response::deny('Accès refusé. Cette entrée du journal concerne un service auquel vous n’êtes pas rattaché.');
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
