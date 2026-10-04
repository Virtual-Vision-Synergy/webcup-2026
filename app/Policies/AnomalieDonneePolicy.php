<?php

namespace App\Policies;

use App\Models\AnomalieDonnee;
use App\Models\User;

/**
 * F85 : anomalies de données, administrateurs uniquement. Seules actions : corriger ou ignorer une anomalie ouverte.
 */
class AnomalieDonneePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, AnomalieDonnee $anomalie): bool
    {
        return $user->isAdmin();
    }

    /**
     * Lancer le contrôle d'intégrité à la demande.
     */
    public function controler(User $user): bool
    {
        return $user->isAdmin();
    }

    public function resoudre(User $user, AnomalieDonnee $anomalie): bool
    {
        return $user->isAdmin() && $anomalie->estOuverte();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AnomalieDonnee $anomalie): bool
    {
        return false;
    }

    public function delete(User $user, AnomalieDonnee $anomalie): bool
    {
        return false;
    }
}
