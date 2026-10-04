<?php

namespace App\Policies;

use App\Models\RecommandationCanicule;
use App\Models\User;

/**
 * F31 : seuls les admins modifient les recommandations écrites (ni création ni suppression : 6 profils × 3 niveaux fixes).
 */
class RecommandationCaniculePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, RecommandationCanicule $recommandation): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, RecommandationCanicule $recommandation): bool
    {
        return $user->isAdmin();
    }
}
