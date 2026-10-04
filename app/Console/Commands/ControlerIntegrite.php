<?php

namespace App\Console\Commands;

use App\Services\ControleIntegrite;
use Illuminate\Console\Command;

/**
 * F85 : repère les données incohérentes (statuts impossibles, références orphelines, dates futures, doublons)
 * et les liste aux admins (Filament → Sécurité → Anomalies de données). Ne corrige rien tout seul.
 */
class ControlerIntegrite extends Command
{
    protected $signature = 'securite:controle-integrite';

    protected $description = 'Repère les données incohérentes et les liste aux admins (aucune correction automatique)';

    public function handle(ControleIntegrite $controle): int
    {
        $resultat = $controle->executer();

        $this->components->info(sprintf(
            '%d anomalie(s) détectée(s), dont %d nouvelle(s) ; %d disparue(s).',
            $resultat['detectees'],
            $resultat['nouvelles'],
            $resultat['disparues'],
        ));

        return self::SUCCESS;
    }
}
