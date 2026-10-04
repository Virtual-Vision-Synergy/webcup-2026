<?php

namespace App\Policies;

use App\Models\ExplicationSimple;
use App\Models\User;

/**
 * F90 : toute personne connectée peut lire une explication active ; seuls les admins les gèrent (Filament).
 */
class ExplicationSimplePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ExplicationSimple $explication): bool
    {
        return $explication->actif || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ExplicationSimple $explication): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ExplicationSimple $explication): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
