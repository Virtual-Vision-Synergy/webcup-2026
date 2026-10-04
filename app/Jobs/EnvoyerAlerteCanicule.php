<?php

namespace App\Jobs;

use App\Models\AlerteCanicule;
use App\Models\User;
use App\Notifications\AlerteCaniculePubliee;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * F31 : prévient, par lots, les citoyens actifs des quartiers touchés par une alerte canicule (pas les autres).
 */
class EnvoyerAlerteCanicule implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $alerteId) {}

    public function handle(): void
    {
        $alerte = AlerteCanicule::query()->with('quartiers:id,nom')->find($this->alerteId);

        // Supprimée ou terminée entre-temps : on n'envoie rien.
        if ($alerte === null || ! $alerte->enCours() || $alerte->quartiers->isEmpty()) {
            return;
        }

        User::query()
            ->citizens()
            ->whereNull('deactivated_at')
            ->whereIn('quartier_id', $alerte->quartiers->modelKeys())
            ->chunkById(200, function (Collection $habitants) use ($alerte): void {
                Notification::send($habitants, new AlerteCaniculePubliee($alerte));
            });
    }
}
