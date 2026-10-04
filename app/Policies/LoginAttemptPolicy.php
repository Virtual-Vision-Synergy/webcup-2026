<?php

namespace App\Policies;

use App\Models\LoginAttempt;
use App\Models\User;

/**
 * F37 : journal des tentatives de connexion, en lecture seule.
 * Consultation : agents et administrateurs. Déblocage d'un couple e-mail + IP : administrateurs uniquement.
 * Aucune création, modification ni suppression manuelle (les lignes sont écrites par le code, purgées par Prunable).
 */
class LoginAttemptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAgent() || $user->isAdmin();
    }

    public function unlock(User $user, ?LoginAttempt $attempt = null): bool
    {
        return $user->isAdmin();
    }
}
