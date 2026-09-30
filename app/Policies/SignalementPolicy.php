<?php

namespace App\Policies;

use App\Models\Signalement;
use App\Models\User;

/**
 * Par défaut : tout utilisateur connecté peut lire et créer ;
 * seuls le propriétaire et les admins peuvent modifier ou supprimer.
 */
class SignalementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Signalement $signalement): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Signalement $signalement): bool
    {
        return $user->isAdmin() || $signalement->user_id === $user->id;
    }

    public function delete(User $user, Signalement $signalement): bool
    {
        return $user->isAdmin() || $signalement->user_id === $user->id;
    }
}
