<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

/**
 * Par défaut : tout utilisateur connecté peut lire et créer ;
 * le propriétaire, les agents et les admins peuvent modifier ;
 * seuls le propriétaire et les admins peuvent supprimer.
 */
class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Service $service): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Service $service): bool
    {
        return $user->isAdmin() || $user->isAgent() || $service->user_id === $user->id;
    }

    public function delete(User $user, Service $service): bool
    {
        return $user->isAdmin() || $service->user_id === $user->id;
    }

    /**
     * Mettre en avant (ou retirer) un service dans le catalogue et sur l'accueil : agents et admins uniquement.
     */
    public function feature(User $user, ?Service $service = null): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }

    /**
     * Rendre un service indisponible ou le rétablir (F63) : administrateurs uniquement.
     */
    public function toggleAvailability(User $user, Service $service): bool
    {
        return $user->isAdmin();
    }

    /**
     * F64 : mettre à jour l'état (disponible, perturbé, indisponible) depuis la fiche du service.
     * F70 : agent rattaché à ce service ou administrateur ; jamais un citoyen.
     */
    public function updateStatus(User $user, Service $service): bool
    {
        return $user->isAdmin() || ($user->isAgent() && $user->canAccessService($service));
    }
}
