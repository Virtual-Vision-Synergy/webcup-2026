<?php

namespace App\Policies;

use App\Models\ActionLog;
use App\Models\User;

/**
 * F69 (A8) : journal des actions en lecture seule, réservé aux administrateurs.
 * Aucune création, modification ni suppression (les lignes sont écrites par ActionLog::record()).
 */
class ActionLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ActionLog $actionLog): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ActionLog $actionLog): bool
    {
        return false;
    }

    public function delete(User $user, ActionLog $actionLog): bool
    {
        return false;
    }
}
