<?php

namespace App\Policies;

use App\Models\Demarche;
use App\Models\User;

/**
 * Une démarche est privée : seul son auteur la consulte, la modifie ou la supprime.
 * Les agents municipaux et les admins consultent toutes les démarches et changent leur statut ;
 * seuls l'auteur et les admins peuvent modifier ou supprimer.
 * La liste (index) est filtrée sur l'auteur pour un habitant.
 */
class DemarchePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Demarche $demarche): bool
    {
        return $demarche->user_id === $user->id || $this->estPersonnel($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Demarche $demarche): bool
    {
        return $user->isAdmin() || $demarche->user_id === $user->id;
    }

    public function delete(User $user, Demarche $demarche): bool
    {
        return $user->isAdmin() || $demarche->user_id === $user->id;
    }

    public function changerStatut(User $user, Demarche $demarche): bool
    {
        return $this->estPersonnel($user);
    }

    /**
     * Agents et admins voient toutes les démarches (traitement par la mairie).
     */
    private function estPersonnel(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }
}
