<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceInterruption;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * F38 : interruptions de démo. Idempotent : un service qui a déjà une interruption (en cours ou passée) est ignoré.
 *   - État civil : incident en cours, retour prévu dans 2 jours, alternative « Mairie annexe du quartier Nord ».
 *   - Médiathèque Ravinala : maintenance en cours, date de retour inconnue.
 *   - Services techniques et voirie : maintenance terminée (historique).
 */
class ServiceInterruptionSeeder extends Seeder
{
    public function run(): void
    {
        $agent = User::query()->where('email', 'agent@example.com')->first()
            ?? User::query()->where('role_id', Role::idFor(Role::AGENT))->first()
            ?? User::query()->where('role', 'admin')->first();

        $services = Service::query()
            ->whereIn('nom', ['État civil', 'Médiathèque Ravinala', 'Services techniques et voirie', 'Mairie annexe du quartier Nord'])
            ->get()
            ->keyBy('nom');

        $this->creer($services->get('État civil'), $agent, [
            'type' => 'incident',
            'motif' => 'Panne du logiciel de délivrance des actes',
            'retour_prevu_at' => now()->addDays(2)->setTime(11, 0),
            'alternative' => 'Les actes urgents (naissances, décès) sont délivrés à la Mairie annexe du quartier Nord, 12 rue du Port, du lundi au vendredi de 8 h à 12 h.',
            'alternative_service_id' => $services->get('Mairie annexe du quartier Nord')?->id,
        ], debut: now()->subHours(3));

        $this->creer($services->get('Médiathèque Ravinala'), $agent, [
            'type' => 'maintenance',
            'motif' => 'Rénovation de la salle de lecture et inventaire des collections',
            'retour_prevu_at' => null,
            'alternative' => 'Les retours de livres restent possibles dans la boîte extérieure. Les prêts en cours sont prolongés automatiquement jusqu’à la réouverture.',
        ], debut: now()->subDay());

        $this->creer($services->get('Services techniques et voirie'), $agent, [
            'type' => 'maintenance',
            'motif' => 'Migration du standard téléphonique',
            'retour_prevu_at' => now()->subDays(12),
            'alternative' => 'Signalez les problèmes de voirie en ligne depuis la rubrique « Signalements ».',
        ], debut: now()->subDays(13), retabli: now()->subDays(12));
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function creer(?Service $service, ?User $agent, array $donnees, CarbonInterface $debut, ?CarbonInterface $retabli = null): void
    {
        if ($service === null || $service->interruptions()->exists()) {
            return;
        }

        $interruption = new ServiceInterruption($donnees);
        $interruption->service()->associate($service);
        $interruption->auteur()->associate($agent);
        $interruption->debut_at = $debut;

        if ($retabli !== null) {
            $interruption->retabli_at = $retabli;
            $interruption->retablissement()->associate($agent);
        }

        $interruption->save();
    }
}
