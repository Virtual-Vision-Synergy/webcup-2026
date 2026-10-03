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

    /*
    | F34 : administration des comptes depuis l'espace agent (/agent/citoyens).
    | Agent : citoyens uniquement. Admin : citoyens et agents. Jamais un admin, jamais soi-même.
    | Aucun changement de rôle ici : il reste réservé à l'admin (updateRole, Filament).
    */

    public function administerAccounts(User $user): bool
    {
        return $user->isAgent() || $user->isAdmin();
    }

    public function viewAccount(User $user, User $model): bool
    {
        return match (true) {
            $user->isAdmin() => $model->isCitoyen() || $model->isAgent(),
            $user->isAgent() => $model->isCitoyen(),
            default => false,
        };
    }

    public function deactivate(User $user, User $model): bool
    {
        return $this->viewAccount($user, $model) && ! $user->is($model) && $model->isActive();
    }

    public function reactivate(User $user, User $model): bool
    {
        return $this->viewAccount($user, $model) && ! $user->is($model) && ! $model->isActive();
    }
}
