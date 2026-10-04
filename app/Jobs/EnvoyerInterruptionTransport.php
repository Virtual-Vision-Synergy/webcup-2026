<?php

namespace App\Jobs;

use App\Models\AbonnementLigne;
use App\Models\InterruptionTransport;
use App\Notifications\LigneTransportInterrompue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

/**
 * F97 : prévient, par lots, les habitants actifs qui ont une ligne interrompue dans leurs trajets habituels (pas les autres).
 */
class EnvoyerInterruptionTransport implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $interruptionId) {}

    public function handle(): void
    {
        $interruption = InterruptionTransport::query()->with('lignes')->find($this->interruptionId);

        // Supprimée ou terminée entre-temps : on n'envoie rien.
        if ($interruption === null || ! $interruption->enCours() || $interruption->lignes->isEmpty()) {
            return;
        }

        $lignes = $interruption->lignes->keyBy('id');

        AbonnementLigne::query()
            ->whereIn('ligne_transport_id', $lignes->keys())
            ->whereHas('user', fn ($query) => $query->whereNull('deactivated_at'))
            ->with('user')
            ->chunkById(200, function (Collection $abonnements) use ($interruption, $lignes): void {
                foreach ($abonnements as $abonnement) {
                    /** @var AbonnementLigne $abonnement */
                    $abonnement->user->notify(new LigneTransportInterrompue($interruption, $lignes[$abonnement->ligne_transport_id]));
                }
            });
    }
}
