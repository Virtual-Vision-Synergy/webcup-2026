<?php

namespace App\Policies;

use App\Models\Actualite;
use App\Models\User;

/**
 * Par défaut : tout utilisateur connecté peut lire et créer ;
 * seuls le propriétaire et les admins peuvent modifier ou supprimer.
 */
class ActualitePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Actualite $actualite): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Actualite $actualite): bool
    {
        return $user->isAdmin() || $actualite->user_id === $user->id;
    }

    public function delete(User $user, Actualite $actualite): bool
    {
        return $user->isAdmin() || $actualite->user_id === $user->id;
    }
}
