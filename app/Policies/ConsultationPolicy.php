<?php

namespace App\Policies;

use App\Models\Consultation;
use App\Models\User;

/**
 * Consultations des habitants (F65) : les agents et les admins créent et publient la décision ;
 * les habitants concernés (toute la ville ou leur quartier) répondent une fois ; résultats aux participants après clôture.
 */
class ConsultationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Consultation $consultation): bool
    {
        return $this->gere($user) || $consultation->concerne($user);
    }

    public function create(User $user): bool
    {
        return $this->gere($user);
    }

    /**
     * Un habitant actif et concerné répond une seule fois, pendant la période d'ouverture.
     */
    public function repondre(User $user, Consultation $consultation): bool
    {
        return $user->isCitoyen()
            && $user->isActive()
            && $consultation->concerne($user)
            && $consultation->estOuverte()
            && ! $consultation->participations()->where('user_id', $user->id)->exists();
    }

    /**
     * Résultats (répartition) et décision : agents et admins, et participants une fois la consultation close.
     */
    public function voirResultats(User $user, Consultation $consultation): bool
    {
        if ($this->gere($user)) {
            return true;
        }

        return $consultation->estCloturee()
            && $consultation->participations()->where('user_id', $user->id)->exists();
    }

    /**
     * La décision de la ville se publie une fois la consultation close.
     */
    public function publierDecision(User $user, Consultation $consultation): bool
    {
        return $this->gere($user) && $consultation->estCloturee();
    }

    private function gere(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }
}
