<?php

namespace App\Policies;

use App\Models\AlerteCanicule;
use App\Models\User;

/**
 * F31 : l'Agence sanitaire (admins, via Filament) déclenche et gère les alertes ; tout habitant connecté choisit son profil.
 * La page publique « Canicule » est en lecture seule et ne passe pas par cette policy.
 */
class AlerteCaniculePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, AlerteCanicule $alerte): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, AlerteCanicule $alerte): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, AlerteCanicule $alerte): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Enregistrer « mon profil » (personne âgée, femme enceinte…) : uniquement sur son propre compte.
     */
    public function choisirProfil(User $user): bool
    {
        return $user->isActive();
    }
}
