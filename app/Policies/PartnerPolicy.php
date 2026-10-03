<?php

namespace App\Policies;

use App\Models\Partner;
use App\Models\User;

/**
 * Partenaires (F74) : consultation publique des partenaires publiés (visiteurs compris, décision assumée :
 * information pratique pour les habitants) ; seuls les agents et les admins créent, modifient ou suppriment.
 */
class PartnerPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Un partenaire non publié n'est visible que des agents et des admins.
     */
    public function view(?User $user, Partner $partner): bool
    {
        return $partner->is_published || ($user !== null && $this->gere($user));
    }

    /**
     * Liste de gestion (publiés et brouillons) de l'espace agent.
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

    public function delete(User $user, Partner $partner): bool
    {
        return $this->gere($user);
    }

    private function gere(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }
}
