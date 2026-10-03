<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * F30 : une notification n'est lisible et modifiable que par son destinataire (notifiable_type + notifiable_id).
 */
class DatabaseNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DatabaseNotification $notification): bool
    {
        return $this->estDestinataire($user, $notification);
    }

    public function update(User $user, DatabaseNotification $notification): bool
    {
        return $this->estDestinataire($user, $notification);
    }

    private function estDestinataire(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && (int) $notification->notifiable_id === $user->id;
    }
}
