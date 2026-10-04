<?php

namespace App\Policies;

use App\Models\GenTestFiche;
use App\Models\User;

/**
 * Lecture ouverte aux invités (--public) ; création réservée aux utilisateurs connectés ;
 * modification et suppression réservées au propriétaire et aux admins.
 * Un invité ne voit pas les fiches au statut par défaut (en attente de validation).
 * Seul un admin peut changer le statut (changerStatut).
 */
class GenTestFichePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, GenTestFiche $genTestFiche): bool
    {
        return $user !== null || $genTestFiche->statut !== GenTestFiche::STATUT_OPTIONS[0];
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, GenTestFiche $genTestFiche): bool
    {
        return $user->isAdmin() || $genTestFiche->user_id === $user->id;
    }

    public function delete(User $user, GenTestFiche $genTestFiche): bool
    {
        return $user->isAdmin() || $genTestFiche->user_id === $user->id;
    }

    public function changerStatut(User $user, GenTestFiche $genTestFiche): bool
    {
        return $user->isAdmin();
    }
}
