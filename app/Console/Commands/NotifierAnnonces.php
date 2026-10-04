<?php

namespace App\Console\Commands;

use App\Services\NotifierAnnonce;
use Illuminate\Console\Command;

/**
 * F30 : notifie les habitants des annonces importantes arrivées à leur début (planifiée chaque minute).
 * Idempotente : une annonce déjà notifiée (notified_at) est ignorée.
 */
class NotifierAnnonces extends Command
{
    protected $signature = 'annonces:notify';

    protected $description = 'Notifie les habitants des annonces importantes qui viennent de commencer (sans doublon)';

    public function handle(NotifierAnnonce $notifier): int
    {
        $nombre = $notifier->traiterEchues();

        $this->components->info($nombre.' annonce(s) importante(s) notifiée(s).');

        return self::SUCCESS;
    }
}
