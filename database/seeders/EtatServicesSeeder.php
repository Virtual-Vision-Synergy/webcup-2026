<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * F64 : état de démonstration des services. La plupart restent disponibles ; État civil est perturbé
 * (forte affluence) et la Médiathèque indisponible (travaux, retour dans 10 jours, alternative proposée).
 * Idempotent : relancé, il remet simplement ces deux états.
 */
class EtatServicesSeeder extends Seeder
{
    public function run(): void
    {
        Service::query()->where('nom', 'Médiathèque Ravinala')->first()?->mettreAJourEtat(
            Service::ETAT_INDISPONIBLE,
            'Fermée pour travaux de rénovation de la salle de lecture.',
            now()->addDays(10)->startOfDay(),
            'Le point lecture de la mairie annexe d\'Isoraka prête et reçoit les retours de livres, du lundi au samedi de 9 h à 17 h.',
        );

        Service::query()->where('nom', 'État civil')->first()?->mettreAJourEtat(
            Service::ETAT_PERTURBE,
            'Forte affluence : délais d\'attente allongés au guichet (environ 1 h 30).',
            null,
            'Faites votre demande d\'acte en ligne : elle est traitée sans passer au guichet.',
        );
    }
}
