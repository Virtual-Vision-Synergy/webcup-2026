<?php

namespace App\Policies;

use App\Models\Remontee;
use App\Models\User;

/**
 * Une remontée (F51) est privée : seul son auteur la consulte, côté habitant.
 * Les agents et les admins consultent toutes les remontées et les traitent (prise en compte, réponse, clôture).
 * Personne ne la modifie ni ne la supprime : la trace de traitement doit rester.
 * La liste « Mes remontées » est filtrée sur l'auteur.
 */
class RemonteePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Remontee $remontee): bool
    {
        return ($remontee->user_id !== null && $remontee->user_id === $user->id) || $this->estPersonnel($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Remontee $remontee): bool
    {
        return false;
    }

    public function delete(User $user, Remontee $remontee): bool
    {
        return false;
    }

    /**
     * Prendre en compte, répondre, clôturer.
     */
    public function traiter(User $user, Remontee $remontee): bool
    {
        return $this->estPersonnel($user);
    }

    private function estPersonnel(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }
}
