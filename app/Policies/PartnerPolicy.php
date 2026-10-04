<?php

namespace App\Policies;

use App\Models\Partner;
use App\Models\User;

/**
 * Partenaires (F74) : consultation publique des partenaires publiés (visiteurs compris, décision assumée :
 * les habitants doivent trouver horaires et adresse sans compte) ; seuls les agents et les admins gèrent la liste.
 */
class PartnerPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Partner $partner): bool
    {
        return $partner->is_published || ($user !== null && $this->gere($user));
    }

    /**
     * Liste de gestion (brouillons compris) de l'espace agent.
     */
    public function manage(User $user): bool
    {
        return $this->gere($user);
    }

    public function create(User $user): bool
    {
        return $this->gere($user);
    }

    public function update(User $user, Partner $partner): bool
    {
        return $this->gere($user);
    }

    /**
     * D09 : suppression réservée à l'administrateur (un agent reçoit 403).
     */
    public function delete(User $user, Partner $partner): bool
    {
        return $user->isAdmin();
    }

    private function gere(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }
}
