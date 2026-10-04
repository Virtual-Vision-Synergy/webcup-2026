<?php

namespace App\Console\Commands;

use App\Models\Demarche;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * F80 : recalcule la priorité suggérée des demandes ouvertes (ancienneté, relances) ; jamais celles fixées par un agent.
 * Idempotente, planifiée toutes les heures.
 */
class PrioriserDemarches extends Command
{
    protected $signature = 'demarches:prioriser';

    protected $description = 'Recalcule la priorité suggérée des demandes ouvertes (sauf celles fixées par un agent)';

    public function handle(): int
    {
        $modifiees = 0;

        Demarche::query()
            ->where('priorite_manuelle', false)
            ->whereIn('statut', Demarche::STATUTS_URGENCE_OUVERTE)
            ->with(['service:id,categorie', 'derniereReponse'])
            ->chunkById(200, function (Collection $demarches) use (&$modifiees): void {
                foreach ($demarches as $demarche) {
                    $avant = $demarche->priorite;
                    $demarche->recalculerPriorite();
                    $modifiees += (int) ($avant !== $demarche->priorite);
                }
            });

        $this->components->info($modifiees.' priorité(s) mise(s) à jour.');

        return self::SUCCESS;
    }
}
