<?php

namespace App\Policies;

use App\Models\Demarche;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Une démarche est privée : seul son auteur la consulte, la modifie ou la supprime.
 * F70 : un agent ne consulte et ne traite que les démarches des services auxquels il est rattaché ;
 * une démarche sans service est réservée à l'admin, qui voit tout.
 * Seuls l'auteur et les admins peuvent modifier ou supprimer.
 * Les listes passent par Demarche::visibleTo() (un agent ne découvre pas les dossiers des autres services).
 */
class DemarchePolicy
{
    /** Motif affiché sur la page 403 (sans rien révéler du dossier). */
    public const MOTIF_AUTRE_SERVICE = 'Accès refusé. Ce dossier relève d’un service auquel vous n’êtes pas rattaché. Si vous pensez devoir y accéder, contactez votre administrateur.';

    public const MOTIF_PRIVE = 'Accès refusé. Cette démarche appartient à un autre habitant.';

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Demarche $demarche): Response
    {
        if ($demarche->user_id === $user->id) {
            return Response::allow();
        }

        return $this->accesService($user, $demarche);
    }

    /**
     * F83 : accusé de réception : l'auteur, le personnel du service concerné et les admins ; autre habitant → 403.
     */
    public function voirAccuse(User $user, Demarche $demarche): Response
    {
        return $this->view($user, $demarche);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Demarche $demarche): bool
    {
        return $user->isAdmin() || $demarche->user_id === $user->id;
    }

    public function delete(User $user, Demarche $demarche): bool
    {
        return $user->isAdmin() || $demarche->user_id === $user->id;
    }

    public function changerStatut(User $user, Demarche $demarche): Response
    {
        return $this->accesService($user, $demarche);
    }

    /**
     * F80 : classer un dossier par priorité (personnel du service ou admin ; un habitant → 403).
     */
    public function changerPriorite(User $user, Demarche $demarche): Response
    {
        return $this->accesService($user, $demarche);
    }

    /**
     * F86 : prendre en charge une urgence médicale (personnel du service, ou admin).
     */
    public function prendreEnCharge(User $user, Demarche $demarche): Response
    {
        if (! $demarche->urgence_medicale) {
            return Response::deny('Seules les urgences médicales se prennent en charge ainsi.');
        }

        return $this->accesService($user, $demarche);
    }

    /**
     * F84 : écrire dans le fil d'une démarche. Le personnel du service y répond ; l'habitant auteur
     * peut répondre à son tour. Tout autre habitant : refusé.
     */
    public function repondre(User $user, Demarche $demarche): Response
    {
        if ($demarche->user_id === $user->id) {
            return Response::allow();
        }

        return $this->accesService($user, $demarche);
    }

    /**
     * F70 : révéler une donnée confidentielle du demandeur (motif obligatoire, consultation journalisée).
     */
    public function viewConfidential(User $user, Demarche $demarche): Response
    {
        return $this->accesService($user, $demarche);
    }

    /**
     * Personnel municipal rattaché au service de la démarche (l'admin a accès à tout).
     */
    private function accesService(User $user, Demarche $demarche): Response
    {
        if (! $user->isAdmin() && ! $user->isAgent()) {
            return Response::deny(self::MOTIF_PRIVE);
        }

        return $user->canAccessService($demarche->service_id)
            ? Response::allow()
            : Response::deny(self::MOTIF_AUTRE_SERVICE);
    }
}
