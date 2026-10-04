<?php

namespace App\Policies;

use App\Models\MotCleService;
use App\Models\User;

/**
 * D10 : seuls les admins consultent et éditent les mots-clés d'orientation (Filament).
 */
class MotCleServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, MotCleService $motCle): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, MotCleService $motCle): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, MotCleService $motCle): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
