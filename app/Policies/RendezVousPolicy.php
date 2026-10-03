<?php

namespace App\Policies;

use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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

    /**
     * F70 : uniquement pour un rendez-vous d'un service de l'agent.
     */
    public function changerStatut(User $user, RendezVous $rendezVous): Response
    {
        $acces = $this->accesService($user, $rendezVous);

        return $acces->allowed() && ! $rendezVous->estConfirme()
            ? Response::deny('Ce rendez-vous n’est plus confirmé : son statut ne peut plus être changé.')
            : $acces;
    }

    /**
     * F70 : révéler le motif saisi par l'habitant (motif de consultation obligatoire, journalisé).
     */
    public function viewConfidential(User $user, RendezVous $rendezVous): Response
    {
        return $this->accesService($user, $rendezVous);
    }

    private function accesService(User $user, RendezVous $rendezVous): Response
    {
        if (! $user->isAgent() && ! $user->isAdmin()) {
            return Response::deny('Accès refusé. Cette action est réservée au personnel municipal.');
        }

        return $user->canAccessService($rendezVous->service_id)
            ? Response::allow()
            : Response::deny(DemarchePolicy::MOTIF_AUTRE_SERVICE);
    }
}
