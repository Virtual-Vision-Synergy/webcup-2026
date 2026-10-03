<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

/**
 * Formulaire de contact vers la mairie (D04) :
 * tout utilisateur connecté peut écrire ; un citoyen ne voit que ses propres messages,
 * les agents et admins voient tous les messages reçus (F22).
 */
class MessagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function viewAll(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }

    public function view(User $user, Message $message): bool
    {
        return $this->viewAll($user) || $message->user_id === $user->id;
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
