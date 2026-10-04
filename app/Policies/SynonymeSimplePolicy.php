<?php

namespace App\Policies;

use App\Models\SynonymeSimple;
use App\Models\User;

/**
 * F90 : seuls les admins consultent et éditent les synonymes simples (Filament).
 */
class SynonymeSimplePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, SynonymeSimple $synonyme): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SynonymeSimple $synonyme): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, SynonymeSimple $synonyme): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
