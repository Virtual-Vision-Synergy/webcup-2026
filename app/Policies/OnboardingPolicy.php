<?php

namespace App\Policies;

use App\Models\User;

/**
 * Parcours de prise en main (D12) : réservé aux habitants, qui n'agissent que sur LEUR parcours
 * (aucun identifiant n'est accepté depuis la requête). Agents et admins n'y ont pas accès.
 */
class OnboardingPolicy
{
    public function view(User $user): bool
    {
        return $user->isCitoyen();
    }

    public function avancer(User $user): bool
    {
        return $user->isCitoyen();
    }
}
