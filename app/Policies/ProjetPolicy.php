<?php

namespace App\Policies;

use App\Models\Projet;
use App\Models\User;

/**
 * Projets de la ville (F67) : consultation publique (visiteurs compris, décision assumée : information citoyenne) ;
 * seuls les agents et les admins créent, modifient ou suppriment.
 * F66 : avis des habitants pendant la consultation, synthèse pour les agents.
 */
class ProjetPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Projet $projet): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->gere($user);
    }

    public function update(User $user, Projet $projet): bool
    {
        return $this->gere($user);
    }

    /**
     * D09 : suppression réservée à l'administrateur (un agent reçoit 403).
     */
    public function delete(User $user, Projet $projet): bool
    {
        return $user->isAdmin();
    }

    /**
     * F66 : un habitant donne (ou modifie) son avis tant que la consultation du projet est ouverte.
     */
    public function donnerAvis(User $user, Projet $projet): bool
    {
        return $projet->consultation_ouverte && $user->isCitoyen() && $user->isActive();
    }

    /**
     * F66 : synthèse des avis (répartition et commentaires) réservée aux agents et aux admins.
     */
    public function voirAvis(User $user, Projet $projet): bool
    {
        return $this->gere($user);
    }

    private function gere(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }
}
