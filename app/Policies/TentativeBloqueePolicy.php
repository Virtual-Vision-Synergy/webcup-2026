<?php

namespace App\Policies;

use App\Models\TentativeBloquee;
use App\Models\User;

/**
 * F81 : journal des envois de formulaires bloqués, en lecture seule, administrateurs uniquement.
 * Aucune création, modification ni suppression manuelle (lignes écrites par le code, purgées par Prunable).
 */
class TentativeBloqueePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, TentativeBloquee $tentative): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, TentativeBloquee $tentative): bool
    {
        return false;
    }

    public function delete(User $user, TentativeBloquee $tentative): bool
    {
        return false;
    }
}
