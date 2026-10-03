<?php

namespace App\Policies;

use App\Models\Signalement;
use App\Models\User;

/**
 * Un signalement est visible par son auteur et par le personnel (agents, admins) qui le traite.
 * Seuls l'auteur et les admins peuvent le modifier ou le supprimer ; seul le personnel change son statut.
 * La liste (index) est filtrée sur l'auteur pour un citoyen.
 */
class SignalementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Signalement $signalement): bool
    {
        return $signalement->user_id === $user->id || $this->estPersonnel($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Signalement $signalement): bool
    {
        return $user->isAdmin() || $signalement->user_id === $user->id;
    }

    public function delete(User $user, Signalement $signalement): bool
    {
        return $user->isAdmin() || $signalement->user_id === $user->id;
    }

    public function changerStatut(User $user, Signalement $signalement): bool
    {
        return $this->estPersonnel($user);
    }

    /**
     * Agents et admins voient tous les signalements (traitement par la mairie).
     */
    private function estPersonnel(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }
}
