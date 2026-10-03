<?php

namespace App\Policies;

use App\Models\Projet;
use App\Models\User;

/**
 * Projets de la ville (F67) : consultation publique (visiteurs compris, décision assumée : information citoyenne) ;
 * seuls les agents et les admins créent, modifient ou suppriment.
 */
class ProjetPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Projet $projet): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->gere($user);
    }

    public function update(User $user, Projet $projet): bool
    {
        return $this->gere($user);
    }

    public function delete(User $user, Projet $projet): bool
    {
        return $this->gere($user);
    }

    private function gere(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }
}
