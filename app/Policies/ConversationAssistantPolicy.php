<?php

namespace App\Policies;

use App\Models\ConversationAssistant;
use App\Models\User;

/**
 * F91 : tout utilisateur connecté peut écrire à l'assistant ; seuls les admins relisent les échanges (anonymisés).
 */
class ConversationAssistantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ConversationAssistant $conversation): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return true;
    }
}
