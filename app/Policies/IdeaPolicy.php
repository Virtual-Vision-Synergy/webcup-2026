<?php

namespace App\Policies;

use App\Models\Idea;
use App\Models\User;

/**
 * Boîte à idées (F68). Lecture publique des idées publiées (décision assumée : participation citoyenne ouverte) ;
 * une idée masquée par la modération n'est visible que par son auteur et le personnel.
 * Proposer : tout utilisateur connecté. Soutenir : mêmes règles que F52 (citoyen, pas sa propre idée, idée ouverte).
 * État, réponse et masquage : agents et admins uniquement, jamais l'auteur.
 */
class IdeaPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Idea $idea): bool
    {
        if (! $idea->estMasquee()) {
            return true;
        }

        return $user !== null && ($idea->user_id === $user->id || $this->estPersonnel($user));
    }

    /**
     * Accusé de réception et suivi personnel : l'auteur seulement.
     */
    public function viewOwn(User $user, Idea $idea): bool
    {
        return $idea->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function soutenir(User $user, Idea $idea): bool
    {
        return $user->isCitoyen()
            && $idea->user_id !== $user->id
            && $idea->estOuverte();
    }

    public function retirerSoutien(User $user, Idea $idea): bool
    {
        return $idea->estSoutenuPar($user);
    }

    public function manage(User $user): bool
    {
        return $this->estPersonnel($user);
    }

    public function updateStatus(User $user, Idea $idea): bool
    {
        return $this->estPersonnel($user);
    }

    public function respond(User $user, Idea $idea): bool
    {
        return $this->estPersonnel($user);
    }

    public function hide(User $user, Idea $idea): bool
    {
        return $this->estPersonnel($user);
    }

    private function estPersonnel(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }
}
