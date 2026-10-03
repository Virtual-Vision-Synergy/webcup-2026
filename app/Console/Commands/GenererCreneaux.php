<?php

namespace App\Console\Commands;

use App\Models\CreneauRendezVous;
use App\Models\Service;
use Carbon\CarbonImmutable;
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

        $fuseau = CreneauRendezVous::fuseau();
        /** @var array<int, int> $joursOuvres */
        $joursOuvres = config('rendez_vous.jours_ouvres', [1, 2, 3, 4, 5]);
        /** @var array<int, array{0: string, 1: string}> $plages */
        $plages = config('rendez_vous.plages', []);

        $dates = [];
        $jour = CarbonImmutable::now($fuseau)->startOfDay();
        while (count($dates) < $jours) {
            if (in_array($jour->dayOfWeekIso, $joursOuvres, true)) {
                $dates[] = $jour;
            }
            $jour = $jour->addDay();
        }

        $maintenant = now();
        $crees = 0;

        Service::query()->prendRendezVous()->each(function (Service $service) use ($dates, $plages, $fuseau, $maintenant, &$crees): void {
            $duree = (int) $service->duree_rendez_vous;
            $lignes = [];

            foreach ($dates as $date) {
                foreach ($plages as [$ouverture, $fermeture]) {
                    $debut = CarbonImmutable::parse($date->format('Y-m-d').' '.$ouverture, $fuseau);
                    $limite = CarbonImmutable::parse($date->format('Y-m-d').' '.$fermeture, $fuseau);

                    while ($debut->addMinutes($duree)->lessThanOrEqualTo($limite)) {
                        $fin = $debut->addMinutes($duree);

                        if ($debut->greaterThan($maintenant)) {
                            $lignes[] = [
                                'service_id' => $service->id,
                                'debut' => $debut->utc()->format('Y-m-d H:i:s'),
                                'fin' => $fin->utc()->format('Y-m-d H:i:s'),
                                'created_at' => $maintenant,
                                'updated_at' => $maintenant,
                            ];
                        }

                        $debut = $fin;
                    }
                }
            }

            foreach (array_chunk($lignes, 200) as $lot) {
                $crees += CreneauRendezVous::query()->insertOrIgnore($lot);
            }
        });

        $this->components->info("{$crees} créneau(x) créé(s) sur {$jours} jour(s) ouvré(s).");

        return self::SUCCESS;
    }
}
