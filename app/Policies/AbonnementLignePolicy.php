<?php

namespace App\Policies;

use App\Models\AbonnementLigne;
use App\Models\User;

/**
 * F97 : chaque habitant gère uniquement ses propres trajets habituels (403 pour celui d'un autre).
 */
class AbonnementLignePolicy
{
    public function create(User $user): bool
    {
        return $user->isActive();
    }

    public function update(User $user, AbonnementLigne $abonnement): bool
    {
        return $abonnement->user_id === $user->id;
    }

    public function delete(User $user, AbonnementLigne $abonnement): bool
    {
        return $abonnement->user_id === $user->id;
    }
}
