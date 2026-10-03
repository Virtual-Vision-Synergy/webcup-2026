<?php

namespace App\Jobs;

use App\Models\Annonce;
use App\Models\User;
use App\Notifications\ImportantAnnouncementPublished;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * F30 : envoie la notification d'une annonce importante aux habitants concernés, par lots.
 * Destinataires : citoyens actifs (pas les agents ni les admins), du quartier ciblé si l'annonce en cible un.
 */
class EnvoyerAnnonceImportante implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $annonceId) {}

    public function handle(): void
    {
        $annonce = Annonce::query()->with('quartier:id,nom')->find($this->annonceId);

        // Supprimée ou dépubliée entre-temps : on n'envoie rien.
        if ($annonce === null || $annonce->statut() !== 'en_cours') {
            return;
        }

        User::query()
            ->citizens()
            ->whereNull('deactivated_at')
            ->when($annonce->quartier_id !== null, fn (Builder $query) => $query->where('quartier_id', $annonce->quartier_id))
            ->chunkById(200, function (Collection $habitants) use ($annonce): void {
                Notification::send($habitants, new ImportantAnnouncementPublished($annonce));
            });
    }
}
