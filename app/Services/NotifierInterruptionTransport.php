<?php

namespace App\Services;

use App\Jobs\EnvoyerInterruptionTransport;
use App\Models\InterruptionTransport;

/**
 * F97 : prévient les abonnés quand une interruption de ligne commence (pas avant son début), une seule fois.
 * notified_at est réservé de façon atomique : jamais deux envois, même si la commande tourne deux fois.
 */
class NotifierInterruptionTransport
{
    public function notifierSiVisible(InterruptionTransport $interruption): bool
    {
        if ($interruption->notified_at !== null || ! $interruption->enCours()) {
            return false;
        }

        $reservee = InterruptionTransport::query()->whereKey($interruption->id)->whereNull('notified_at')->update(['notified_at' => now()]);

        if ($reservee !== 1) {
            return false;
        }

        EnvoyerInterruptionTransport::dispatch($interruption->id);

        return true;
    }

    /**
     * Interruptions programmées arrivées à leur début et pas encore notifiées.
     */
    public function traiterEchues(): int
    {
        return InterruptionTransport::query()
            ->enCours()
            ->whereNull('notified_at')
            ->get()
            ->filter(fn (InterruptionTransport $interruption): bool => $this->notifierSiVisible($interruption))
            ->count();
    }
}
