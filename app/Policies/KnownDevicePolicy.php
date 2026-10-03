<?php

namespace App\Policies;

use App\Models\KnownDevice;
use App\Models\User;

/**
 * F54 : chacun ne voit et n'agit que sur ses propres appareils (l'appareil d'un autre donne 403).
 * Aucune création ni modification manuelle : les appareils sont écrits par DeviceRecognizer.
 */
class KnownDevicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, KnownDevice $knownDevice): bool
    {
        return $knownDevice->user_id === $user->id;
    }

    /**
     * « Ce n'était pas moi ».
     */
    public function report(User $user, KnownDevice $knownDevice): bool
    {
        return $knownDevice->user_id === $user->id;
    }

    /**
     * « Retirer cet appareil » (révocation).
     */
    public function delete(User $user, KnownDevice $knownDevice): bool
    {
        return $knownDevice->user_id === $user->id;
    }

    /**
     * « Déconnecter tous les autres appareils ».
     */
    public function logoutOthers(User $user): bool
    {
        return true;
    }
}
