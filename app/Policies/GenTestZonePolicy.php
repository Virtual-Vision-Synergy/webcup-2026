<?php

namespace App\Policies;

use App\Models\GenTestZone;
use App\Models\User;

/**
 * Par défaut : tout utilisateur connecté peut lire et créer ;
 * seuls le propriétaire et les admins peuvent modifier ou supprimer.
 */
class GenTestZonePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GenTestZone $genTestZone): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, GenTestZone $genTestZone): bool
    {
        return $user->isAdmin() || $genTestZone->user_id === $user->id;
    }

    public function delete(User $user, GenTestZone $genTestZone): bool
    {
        return $user->isAdmin() || $genTestZone->user_id === $user->id;
    }
}
