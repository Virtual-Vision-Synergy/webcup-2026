<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * F70 : rattache un agent existant à un ou plusieurs services (production, sans tinker). Journalisé dans F47.
 * L'interface équivalente est la fiche de l'agent dans /agent/citoyens/{id} (admin uniquement).
 */
class AffecterService extends Command
{
    protected $signature = 'app:affecter-service
        {email : E-mail d\'un agent déjà inscrit}
        {services* : Slugs ou noms des services (ex. etat-civil "Action sociale (CCAS)")}
        {--remplacer : Remplace les services actuels au lieu de les compléter}';

    protected $description = 'Rattache un agent à des services (permissions fines F70)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $agent = User::where('email', $email)->first();

        if (! $agent?->isAgent()) {
            $this->error("Aucun agent avec l'adresse {$email}.");

            return self::FAILURE;
        }

        /** @var list<string> $saisies */
        $saisies = (array) $this->argument('services');
        $services = Service::query()->whereIn('slug', $saisies)->orWhereIn('nom', $saisies)->pluck('id')->all();

        if (count($services) === 0) {
            $this->error('Aucun service ne correspond.');

            return self::FAILURE;
        }

        $ids = $this->option('remplacer') ? $services : array_merge($agent->serviceIds(), $services);
        $agent->affecterServices(array_values(array_unique(array_map('intval', $ids))));

        $this->info("{$email} couvre maintenant : ".$agent->services()->orderBy('nom')->pluck('nom')->implode(', '));

        return self::SUCCESS;
    }
}
