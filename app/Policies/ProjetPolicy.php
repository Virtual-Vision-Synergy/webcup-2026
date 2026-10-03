<?php

namespace App\Policies;

use App\Models\Projet;
use App\Models\User;

/**
 * Projets de la ville (F67), décision assumée : la liste et les fiches sont publiques (visiteurs compris).
 * Seuls les agents et les administrateurs créent, modifient et suppriment les projets.
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
        return $user->isAgent() || $user->isAdmin();
    }
}
