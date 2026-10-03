<?php

namespace App\Policies;

use App\Models\Annonce;
use App\Models\User;

/**
 * Les messages généraux sont gérés par les agents et les administrateurs uniquement.
 * Les habitants (et les visiteurs) les voient seulement dans le bandeau.
 */
class AnnoncePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->gere($user);
    }

    public function create(User $user): bool
    {
        return $this->gere($user);
    }

    public function update(User $user, Annonce $annonce): bool
    {
        return $this->gere($user);
    }

    public function delete(User $user, Annonce $annonce): bool
    {
        return $this->gere($user);
    }

    private function gere(User $user): bool
    {
        return $user->isAgent() || $user->isAdmin();
    }
}
