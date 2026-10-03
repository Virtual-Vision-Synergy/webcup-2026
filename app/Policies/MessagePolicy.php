<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

/**
 * Par défaut : tout utilisateur connecté peut lire et créer ;
 * seuls le propriétaire et les admins peuvent modifier ou supprimer.
 */
class MessagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Message $message): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Message $message): bool
    {
        return $user->isAdmin() || $message->user_id === $user->id;
    }

    public function delete(User $user, Message $message): bool
    {
        return $user->isAdmin() || $message->user_id === $user->id;
    }
}
