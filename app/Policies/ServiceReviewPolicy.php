<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\ServiceReview;
use App\Models\User;

/**
 * Avis des habitants sur les services (F76).
 * Donner son avis : tout utilisateur connecté (badge « A utilisé ce service » si l'usage est vérifié).
 * Modifier : l'auteur seul, tant que l'avis n'est pas masqué. Jamais de suppression côté habitant.
 * Répondre, masquer, réafficher : agent rattaché au service concerné (F70) ou administrateur.
 */
class ServiceReviewPolicy
{
    /**
     * « Mes avis sur les services » : la liste est toujours filtrée sur l'utilisateur connecté.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Donner (ou modifier) son avis sur ce service : un seul par habitant, le formulaire bascule
     * sur update quand l'avis existe déjà.
     */
    public function create(User $user, Service $service): bool
    {
        return ! ServiceReview::query()->where('user_id', $user->id)->where('service_id', $service->id)->exists();
    }

    public function update(User $user, ServiceReview $review): bool
    {
        return $review->user_id === $user->id && ! $review->estMasque();
    }

    /**
     * Espace agent : liste des avis des services couverts.
     */
    public function moderate(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }

    public function respond(User $user, ServiceReview $review): bool
    {
        return $this->traiteLeService($user, $review);
    }

    public function hide(User $user, ServiceReview $review): bool
    {
        return $this->traiteLeService($user, $review);
    }

    public function unhide(User $user, ServiceReview $review): bool
    {
        return $this->traiteLeService($user, $review);
    }

    private function traiteLeService(User $user, ServiceReview $review): bool
    {
        return $user->isAdmin() || ($user->isAgent() && $user->canAccessService($review->service_id));
    }
}
