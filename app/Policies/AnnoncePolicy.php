<?php

namespace App\Policies;

use App\Models\Annonce;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Les messages généraux et alertes sont gérés par les agents et les administrateurs uniquement.
 * Les habitants (et les visiteurs) les voient dans le bandeau et, pour une annonce en cours, sur sa page publique.
 */
class AnnoncePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->gere($user);
    }

    /**
     * Page publique /alertes/{annonce} (F29), visiteurs compris : uniquement pendant la diffusion.
     * Hors période, on répond 404 pour ne rien révéler des messages programmés ou expirés.
     */
    public function view(?User $user, Annonce $annonce): Response
    {
        return $annonce->statut() === 'en_cours'
            ? Response::allow()
            : Response::denyAsNotFound('Cette alerte n’est pas ou plus en ligne.');
    }

    public function create(User $user): bool
    {
        return $this->gere($user);
    }

    public function update(User $user, Annonce $annonce): bool
    {
        return $this->gere($user);
    }

    public function delete(User $user, Annonce $annonce): bool
    {
        return $this->gere($user);
    }

    private function gere(User $user): bool
    {
        return $user->isAgent() || $user->isAdmin();
    }
}
