<?php

namespace App\Policies;

use App\Models\InterruptionTransport;
use App\Models\User;

/**
 * F97 : les interruptions de lignes sont déclarées et gérées dans Filament (admins) ;
 * les habitants les consultent sur la page Transports, qui ne passe pas par cette policy.
 */
class InterruptionTransportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, InterruptionTransport $interruption): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, InterruptionTransport $interruption): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, InterruptionTransport $interruption): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
