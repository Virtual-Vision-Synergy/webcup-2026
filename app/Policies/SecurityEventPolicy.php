<?php

namespace App\Policies;

use App\Models\SecurityEvent;
use App\Models\User;

/**
 * F85 : événements de sécurité, en lecture seule, administrateurs uniquement.
 * Aucune création, modification ni suppression manuelle (lignes écrites par SurveillanceSecurite, purgées par Prunable).
 */
class SecurityEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, SecurityEvent $event): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SecurityEvent $event): bool
    {
        return false;
    }

    public function delete(User $user, SecurityEvent $event): bool
    {
        return false;
    }
}
