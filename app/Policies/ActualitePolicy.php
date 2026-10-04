<?php

namespace App\Policies;

use App\Models\Actualite;
use App\Models\User;

/**
 * Tout utilisateur connecté peut lire ; agents et admins publient (D09 : un citoyen reçoit 403, comme pour les services) ;
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
        return $user->isAdmin() || $user->isAgent();
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
