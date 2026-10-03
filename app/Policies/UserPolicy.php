<?php

namespace App\Policies;

use App\Models\User;

/**
 * Gestion des comptes (Filament /admin/users) : réservée aux administrateurs.
 * Un admin ne peut ni supprimer son propre compte ni changer son propre rôle.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $user->is($model);
    }

    public function updateRole(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $user->is($model);
    }
}
