<?php

namespace App\Console\Commands;

use App\Services\NotifierInterruptionTransport;
use Illuminate\Console\Command;

/**
 * F97 : notifie les abonnés des interruptions de lignes programmées arrivées à leur début. Idempotente.
 */
class NotifierInterruptionsTransport extends Command
{
    protected $signature = 'transports:notify';

    protected $description = 'Notifie les habitants abonnés des interruptions de lignes arrivées à leur début';

    public function handle(NotifierInterruptionTransport $notifier): int
    {
        $this->info($notifier->traiterEchues().' interruption(s) de ligne notifiée(s).');

        return self::SUCCESS;
    }
}
