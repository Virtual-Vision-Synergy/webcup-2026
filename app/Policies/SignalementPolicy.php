<?php

namespace App\Policies;

use App\Models\Signalement;
use App\Models\User;

/**
 * Un signalement est visible par son auteur et par le personnel (agents, admins) qui le traite.
 * Seuls l'auteur et les admins peuvent le modifier ou le supprimer ; seul le personnel change son statut.
 * La liste (index) est filtrée sur l'auteur pour un citoyen.
 *
 * Soutiens (F52) : un citoyen peut soutenir une demande encore ouverte déposée par un autre habitant,
 * et retirer uniquement son propre soutien.
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

    /**
     * Suivi citoyen « Mes demandes » (D11) : uniquement l'auteur, même pour un agent ou un admin
     * (le personnel consulte les demandes depuis son propre espace).
     */
    public function viewOwn(User $user, Signalement $signalement): bool
    {
        return $signalement->user_id === $user->id;
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

    public function soutenir(User $user, Signalement $signalement): bool
    {
        return $user->isCitoyen()
            && $signalement->user_id !== $user->id
            && $signalement->estOuvert();
    }

    public function retirerSoutien(User $user, Signalement $signalement): bool
    {
        return $signalement->estSoutenuPar($user);
    }

    /**
     * Agents et admins voient tous les signalements (traitement par la mairie).
     */
    private function estPersonnel(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }
}
