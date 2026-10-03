<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

/**
 * Annuaire public : tout le monde (même invité) consulte la liste et les fiches.
 * Seule la Mairie (admins) crée, modifie et supprime les services.
 */
class ServicePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Service $service): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Service $service): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Service $service): bool
    {
        return $user->isAdmin();
    }
}
