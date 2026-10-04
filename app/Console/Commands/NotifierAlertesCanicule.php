<?php

namespace App\Console\Commands;

use App\Services\NotifierCanicule;
use Illuminate\Console\Command;

/**
 * F31 : notifie les habitants des alertes canicule programmées arrivées à leur début. Idempotente.
 */
class NotifierAlertesCanicule extends Command
{
    protected $signature = 'canicule:notify';

    protected $description = 'Notifie les habitants des alertes canicule arrivées à leur début';

    public function handle(NotifierCanicule $notifier): int
    {
        $this->info($notifier->traiterEchues().' alerte(s) canicule notifiée(s).');

        return self::SUCCESS;
    }
}
