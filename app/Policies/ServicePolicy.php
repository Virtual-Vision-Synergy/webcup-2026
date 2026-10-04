<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

/**
 * Tout utilisateur connecté peut lire ; agents et admins peuvent créer ;
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

    /**
     * F32 : un citoyen consulte le catalogue mais n'y ajoute pas de service.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }

    public function update(User $user, Service $service): bool
    {
        return $user->isAdmin() || $user->isAgent() || $service->user_id === $user->id;
    }

    /**
     * D09 : suppression réservée à l'administrateur (un agent reçoit 403).
     */
    public function delete(User $user, Service $service): bool
    {
        return $user->isAdmin();
    }

    /**
     * Mettre en avant (ou retirer) un service dans le catalogue et sur l'accueil : agents et admins uniquement.
     */
    public function feature(User $user, ?Service $service = null): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }

    /**
     * Marquer un service indisponible (maintenance, incident) ou le rétablir (F38) : agents et admins uniquement.
     * Sans service : accès à la liste des disponibilités de l'espace agent.
     */
    public function manageAvailability(User $user, ?Service $service = null): bool
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
     * F27 : saisir les traductions de la fiche (English…) : agent rattaché à ce service (F70) ou administrateur ;
     * jamais un citoyen.
     */
    public function translate(User $user, Service $service): bool
    {
        return $user->isAdmin() || ($user->isAgent() && $user->canAccessService($service));
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
