<?php

namespace App\Policies;

use App\Models\RegleAssistant;
use App\Models\User;

/**
 * F91 : seuls les admins consultent et éditent les règles de l'assistant (Filament).
 */
class RegleAssistantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, RegleAssistant $regle): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, RegleAssistant $regle): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, RegleAssistant $regle): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
