<?php

namespace App\Policies;

use App\Models\AvisProjet;
use App\Models\User;

/**
 * Avis des habitants sur les projets (F66). L'habitant ne voit que ses propres avis ;
 * la synthèse par projet est dans ProjetPolicy::voirAvis (agents et admins).
 */
class AvisProjetPolicy
{
    /**
     * « Mes avis » : la liste est toujours filtrée sur l'utilisateur connecté.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AvisProjet $avis): bool
    {
        return $avis->user_id === $user->id;
    }
}
