<?php

namespace App\Console\Commands;

use App\Services\RappelsRendezVous;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * F40 : envoie le rappel des rendez-vous confirmés qui approchent (planifiée toutes les 5 minutes).
 * Idempotente : un rendez-vous déjà rappelé (reminder_sent_at) est ignoré.
 */
class EnvoyerRappelsRendezVous extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Envoie le rappel des rendez-vous confirmés qui commencent bientôt (sans doublon)';

    public function handle(RappelsRendezVous $rappels): int
    {
        $resultat = $rappels->envoyerTous();
        $resume = $resultat['envoyes'].' rappel(s) envoyé(s), '.$resultat['erreurs'].' erreur(s)';

        Log::info('F40 rappels de rendez-vous : '.$resume, $resultat);

        if ($resultat['erreurs'] > 0) {
            $this->components->warn($resume.'.');
        } else {
            $this->components->info($resume.'.');
        }

        return self::SUCCESS;
    }
}
