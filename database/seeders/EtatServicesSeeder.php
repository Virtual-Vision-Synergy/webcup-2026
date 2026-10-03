<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * F64 : état de démonstration des services. La plupart restent disponibles ; Urbanisme est perturbé
 * (forte affluence) et la Médiathèque indisponible (travaux, retour dans 10 jours, alternative proposée).
 * Un service déjà interrompu par ServiceInterruptionSeeder (F38) n'est pas touché : son interruption fait foi.
 * Idempotent : relancé, il remet simplement ces deux états.
 */
class EtatServicesSeeder extends Seeder
{
    public function run(): void
    {
        $this->service('Médiathèque Ravinala')?->mettreAJourEtat(
            Service::ETAT_INDISPONIBLE,
            'Fermée pour travaux de rénovation de la salle de lecture.',
            now()->addDays(10)->startOfDay(),
            'Le point lecture de la mairie annexe d\'Isoraka prête et reçoit les retours de livres, du lundi au samedi de 9 h à 17 h.',
        );

        $this->service('Urbanisme')?->mettreAJourEtat(
            Service::ETAT_PERTURBE,
            'Forte affluence : délais d\'instruction des permis allongés (environ 3 semaines).',
            null,
            'Déposez votre dossier en ligne : il est enregistré sans passer au guichet.',
        );
    }

    private function service(string $nom): ?Service
    {
        $service = Service::query()->where('nom', $nom)->first();

        return $service?->interruptionEnCours() === null ? $service : null;
    }
}
