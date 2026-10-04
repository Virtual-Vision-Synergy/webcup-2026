<?php

namespace App\Console\Commands;

use App\Services\Sauvegardes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * F87 : vérifie la dernière sauvegarde automatique (si pas encore vérifiée) et alerte les admins
 * si aucune sauvegarde récente n'existe (au plus une alerte par heure).
 */
class SurveillerSauvegardes extends Command
{
    protected $signature = 'sauvegardes:surveiller';

    protected $description = 'Vérifie la dernière sauvegarde de la base et alerte les admins si elle est absente, ancienne ou incomplète';

    public function handle(Sauvegardes $sauvegardes): int
    {
        $etat = $sauvegardes->etat();

        if ($etat['niveau'] === 'critique') {
            if (Cache::add('sauvegardes:alerte-retard', true, now()->addHour())) {
                $derniere = $sauvegardes->derniere();
                $sauvegardes->alerterAdmins(
                    'Aucune sauvegarde récente',
                    $derniere === null
                        ? 'Aucune sauvegarde de la base n’a été trouvée sur le serveur.'
                        : 'La dernière sauvegarde date du '.$derniere['date']->format('d/m/Y à H:i').' (plus d’une heure).',
                );
            }

            $this->components->warn($etat['libelle']);
        }

        if (! $sauvegardes->derniereDejaVerifiee() && $sauvegardes->derniere() !== null) {
            $verification = $sauvegardes->verifierDerniere();
            $this->components->info($verification->rapport);
        }

        return self::SUCCESS;
    }
}
