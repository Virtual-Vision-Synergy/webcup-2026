<?php

namespace App\Console\Commands;

use App\Models\CreneauRendezVous;
use App\Models\Service;
use Illuminate\Console\Command;

/**
 * Crée les créneaux de rendez-vous (F39) des prochains jours ouvrés pour chaque service qui prend des
 * rendez-vous, selon les plages de config/rendez_vous.php (heure locale) et la durée du service.
 * Idempotente : un créneau déjà présent (même service, même début) est ignoré (index unique).
 */
class GenererCreneaux extends Command
{
    protected $signature = 'appointments:generate-slots
        {--days= : Nombre de jours ouvrés à couvrir (défaut : config rendez_vous.jours)}';

    protected $description = 'Génère les créneaux de rendez-vous des prochains jours ouvrés (sans doublon)';

    public function handle(): int
    {
        $jours = (int) ($this->option('days') ?: config('rendez_vous.jours', 14));

        if ($jours < 1 || $jours > 60) {
            $this->components->error('--days doit être compris entre 1 et 60.');

            return self::FAILURE;
        }

        $crees = 0;

        Service::query()->prendRendezVous()->each(function (Service $service) use ($jours, &$crees): void {
            $crees += CreneauRendezVous::genererPour($service, $jours);
        });

        $this->components->info("{$crees} créneau(x) créé(s) sur {$jours} jour(s) ouvré(s).");

        return self::SUCCESS;
    }
}
