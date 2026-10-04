<?php

namespace App\Services;

use App\Jobs\EnvoyerAlerteCanicule;
use App\Models\AlerteCanicule;

/**
 * F31 : prévient les habitants quand une alerte canicule devient visible (pas avant son début), une seule fois.
 * notified_at est réservé de façon atomique : jamais deux envois, même si la commande tourne deux fois.
 */
class NotifierCanicule
{
    public function notifierSiVisible(AlerteCanicule $alerte): bool
    {
        if ($alerte->notified_at !== null || ! $alerte->enCours()) {
            return false;
        }

        $reservee = AlerteCanicule::query()->whereKey($alerte->id)->whereNull('notified_at')->update(['notified_at' => now()]);

        if ($reservee !== 1) {
            return false;
        }

        EnvoyerAlerteCanicule::dispatch($alerte->id);

        return true;
    }

    /**
     * Alertes programmées arrivées à leur début et pas encore notifiées.
     */
    public function traiterEchues(): int
    {
        return AlerteCanicule::query()
            ->enCours()
            ->whereNull('notified_at')
            ->get()
            ->filter(fn (AlerteCanicule $alerte): bool => $this->notifierSiVisible($alerte))
            ->count();
    }
}
