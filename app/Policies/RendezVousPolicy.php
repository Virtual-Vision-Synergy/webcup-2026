<?php

namespace App\Policies;

use App\Models\RendezVous;
use App\Models\User;

/**
 * Un habitant ne voit et n'annule que ses propres rendez-vous (sinon 403).
 * Les agents et admins consultent l'agenda du jour et marquent un rendez-vous honoré ou absent.
 * Aucune modification ni suppression : un rendez-vous s'annule.
 */
class RendezVousPolicy
{
    /** Liste « Mes rendez-vous » : toujours filtrée sur l'utilisateur connecté. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RendezVous $rendezVous): bool
    {
        return $rendezVous->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function cancel(User $user, RendezVous $rendezVous): bool
    {
        return $rendezVous->user_id === $user->id && $rendezVous->estAnnulable();
    }

    /** Agenda des rendez-vous (espace agent). */
    public function viewAgenda(User $user): bool
    {
        return $user->isAgent() || $user->isAdmin();
    }

    public function changerStatut(User $user, RendezVous $rendezVous): bool
    {
        return ($user->isAgent() || $user->isAdmin()) && $rendezVous->estConfirme();
    }
}
