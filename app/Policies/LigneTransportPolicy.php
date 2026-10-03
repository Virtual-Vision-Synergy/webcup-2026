<?php

namespace App\Policies;

use App\Models\LigneTransport;
use App\Models\User;

/**
 * Tout utilisateur connecté consulte les lignes ;
 * seuls les agents et les admins publient, modifient ou suppriment (dont l'état du trafic).
 */
class LigneTransportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LigneTransport $ligneTransport): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }

    public function update(User $user, LigneTransport $ligneTransport): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }

    public function delete(User $user, LigneTransport $ligneTransport): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }
}
