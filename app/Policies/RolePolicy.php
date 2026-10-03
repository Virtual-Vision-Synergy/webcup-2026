<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * La gestion des rôles est réservée aux administrateurs.
 * Les rôles de base (citoyen, agent, admin) et les rôles encore attribués ne sont pas supprimables.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Role $role): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Role $role): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->isAdmin()
            && ! in_array($role->code, Role::CODES, true)
            && ! $role->users()->exists();
    }
}
