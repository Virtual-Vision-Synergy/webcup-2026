<?php

namespace App\Policies;

use App\Models\PartnerOffering;
use App\Models\User;

/**
 * Services partenaires (F99).
 *  - public (invités compris) : services publiés d'un partenaire publié ;
 *  - compte partenaire : gère uniquement les services de SON partenaire (users.partner_id) ;
 *  - admin : gère les services de tous les partenaires ;
 *  - agent et citoyen : aucun accès à la gestion (403).
 */
class PartnerOfferingPolicy
{
    /**
     * Liste de gestion de l'espace partenaire.
     */
    public function viewAny(User $user): bool
    {
        return $this->accedeEspace($user);
    }

    public function view(?User $user, PartnerOffering $partnerOffering): bool
    {
        if ($partnerOffering->is_published && $partnerOffering->partner->is_published) {
            return true;
        }

        return $user !== null && $this->gere($user, $partnerOffering);
    }

    public function create(User $user): bool
    {
        return $this->accedeEspace($user);
    }

    public function update(User $user, PartnerOffering $partnerOffering): bool
    {
        return $this->gere($user, $partnerOffering);
    }

    /**
     * Changement d'état rapide depuis la liste (Disponible / Complet / Indisponible jusqu'au …).
     */
    public function changeStatus(User $user, PartnerOffering $partnerOffering): bool
    {
        return $this->gere($user, $partnerOffering);
    }

    public function delete(User $user, PartnerOffering $partnerOffering): bool
    {
        return $this->gere($user, $partnerOffering);
    }

    /**
     * Seul un admin choisit le partenaire d'un service (un compte partenaire est limité au sien).
     */
    public function choosePartner(User $user): bool
    {
        return $user->isAdmin();
    }

    private function accedeEspace(User $user): bool
    {
        return $user->isAdmin() || ($user->isPartenaire() && $user->partner_id !== null);
    }

    private function gere(User $user, PartnerOffering $partnerOffering): bool
    {
        return $user->isAdmin() || $user->gerePartenaire((int) $partnerOffering->partner_id);
    }
}
