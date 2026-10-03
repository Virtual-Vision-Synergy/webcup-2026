<?php

namespace App\Policies;

use App\Models\AlerteCanicule;
use App\Models\User;

/**
 * Tout utilisateur connecté consulte les alertes ;
 * seuls les agents et les admins en publient, et seul l'auteur (ou un admin) modifie ou supprime.
 */
class AlerteCaniculePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AlerteCanicule $alerte): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }

    public function update(User $user, AlerteCanicule $alerte): bool
    {
        return $user->isAdmin() || ($user->isAgent() && $alerte->user_id === $user->id);
    }

    public function delete(User $user, AlerteCanicule $alerte): bool
    {
        return $this->update($user, $alerte);
    }
}
