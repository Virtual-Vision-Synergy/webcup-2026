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

    /**
     * F100 : fil des derniers événements dans l'espace agent (données masquées, lecture seule).
     * Agents et administrateurs ; un habitant reçoit un 403.
     */
    public function consulterFil(User $user): bool
    {
        return $user->isAgent() || $user->isAdmin();
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
