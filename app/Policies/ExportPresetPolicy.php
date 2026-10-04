<?php

namespace App\Policies;

use App\Models\ExportPreset;
use App\Models\User;

/**
 * F88 : exports de données de suivi réservés aux agents et admins (Gate « exportDemarches »).
 * Un préréglage n'est visible, utilisable et supprimable que par son auteur (même un admin reçoit un 403).
 */
class ExportPresetPolicy
{
    /**
     * Exporter les données de suivi (page, aperçu, téléchargement) : agents et admins ; citoyen → 403.
     */
    public function export(User $user): bool
    {
        return $this->estPersonnel($user);
    }

    public function viewAny(User $user): bool
    {
        return $this->estPersonnel($user);
    }

    public function view(User $user, ExportPreset $exportPreset): bool
    {
        return $this->estPersonnel($user) && $exportPreset->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->estPersonnel($user);
    }

    public function update(User $user, ExportPreset $exportPreset): bool
    {
        return $this->view($user, $exportPreset);
    }

    public function delete(User $user, ExportPreset $exportPreset): bool
    {
        return $this->view($user, $exportPreset);
    }

    private function estPersonnel(User $user): bool
    {
        return $user->isAgent() || $user->isAdmin();
    }
}
