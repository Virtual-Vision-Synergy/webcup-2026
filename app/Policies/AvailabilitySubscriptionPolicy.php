<?php

namespace App\Policies;

use App\Models\AvailabilitySubscription;
use App\Models\PartnerOffering;
use App\Models\User;

/**
 * « Me prévenir quand disponible » (F99) : chacun ne gère que SES abonnements.
 */
class AvailabilitySubscriptionPolicy
{
    /**
     * S'abonner à un service publié qui n'est pas disponible.
     */
    public function create(User $user, PartnerOffering $offering): bool
    {
        return $user->isActive() && $offering->is_published && ! $offering->isAvailable();
    }

    public function delete(User $user, AvailabilitySubscription $subscription): bool
    {
        return $subscription->user_id === $user->id;
    }
}
