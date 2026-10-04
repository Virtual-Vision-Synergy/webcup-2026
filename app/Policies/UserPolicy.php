<?php

namespace App\Policies;

use App\Models\User;

/**
 * Gestion des comptes (Filament /admin/users) : réservée aux administrateurs.
 * Un admin ne peut ni supprimer son propre compte ni changer son propre rôle.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $user->is($model);
    }

    public function updateRole(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $user->is($model);
    }

    /*
    | F34 : administration des comptes depuis l'espace agent (/agent/citoyens).
    | Agent : citoyens uniquement. Admin : citoyens et agents. Jamais un admin, jamais soi-même.
    | Aucun changement de rôle ici : il reste réservé à l'admin (updateRole, Filament).
    */

    public function administerAccounts(User $user): bool
    {
        return $user->isAgent() || $user->isAdmin();
    }

    public function viewAccount(User $user, User $model): bool
    {
        return match (true) {
            $user->isAdmin() => $model->isCitoyen() || $model->isAgent(),
            $user->isAgent() => $model->isCitoyen(),
            default => false,
        };
    }

    /**
     * F70 : révéler le téléphone ou l'e-mail d'un compte (motif obligatoire, consultation journalisée).
     */
    public function viewConfidential(User $user, User $model): bool
    {
        return $this->viewAccount($user, $model);
    }

    /**
     * F70 : rattacher un agent à des services. Réservé à l'admin ; jamais un citoyen ni un admin.
     */
    public function assignServices(User $user, User $model): bool
    {
        return $user->isAdmin() && $model->isAgent();
    }

    public function deactivate(User $user, User $model): bool
    {
        return $this->viewAccount($user, $model) && ! $user->is($model) && $model->isActive();
    }

    public function reactivate(User $user, User $model): bool
    {
        return $this->viewAccount($user, $model) && ! $user->is($model) && ! $model->isActive();
    }

    /**
     * F85 : verrouillage temporaire d'un compte suspect (et fermeture de ses sessions). Admin seul,
     * jamais sur soi-même ni sur un autre admin (pas de prise de contrôle du panneau).
     */
    public function verrouiller(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $user->is($model) && ! $model->isAdmin();
    }

    public function deverrouiller(User $user, User $model): bool
    {
        return $user->isAdmin() && $model->estVerrouille();
    }

    /*
    | F71 : comptes des habitants sans e-mail, créés par un agent (un par un ou par import CSV).
    | Un nouveau code d'activation ne peut être émis que pour un citoyen SANS e-mail géré par l'acteur (jamais soi-même) :
    | un compte avec e-mail garde la réinitialisation du mot de passe par e-mail, sans prise de contrôle possible par un agent.
    */

    public function createResidentAccounts(User $user): bool
    {
        return $user->isAgent() || $user->isAdmin();
    }

    public function issueActivationCode(User $user, User $model): bool
    {
        return $this->viewAccount($user, $model) && $model->isCitoyen() && ! $model->aUnEmail()
            && ! $user->is($model) && $model->isActive();
    }

    /*
    | F55 : téléchargement de ses données personnelles. Uniquement les siennes, quel que soit le rôle
    | (même un admin ne télécharge pas l'export d'un autre habitant par cette voie).
    */

    public function exportPersonalData(User $user, User $model): bool
    {
        return $user->is($model);
    }
}
