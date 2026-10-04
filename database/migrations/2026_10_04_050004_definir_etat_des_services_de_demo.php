<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Données uniquement (F64) : état de démonstration de deux services de l'annuaire, comme EtatServicesSeeder en local.
 * Ne touche qu'un service encore pleinement disponible et sans interruption F38 en cours (un état choisi par un agent n'est jamais écrasé).
 */
return new class extends Migration
{
    public function up(): void
    {
        $maintenant = now();

        // F38 : un service déjà interrompu depuis l'espace agent garde son interruption.
        $interruptionEnCours = fn ($query) => $query->select(DB::raw(1))
            ->from('service_interruptions')
            ->whereColumn('service_interruptions.service_id', 'services.id')
            ->whereNull('retabli_at');

        DB::table('services')
            ->where('nom', 'Médiathèque Ravinala')
            ->whereNull('indisponible_depuis')
            ->whereNull('perturbe_depuis')
            ->whereNotExists($interruptionEnCours)
            ->update([
                'indisponible_depuis' => $maintenant,
                'motif_indisponibilite' => 'Fermée pour travaux de rénovation de la salle de lecture.',
                'retour_prevu_le' => $maintenant->copy()->addDays(10)->toDateString(),
                'alternative_texte' => 'Le point lecture de la mairie annexe d\'Isoraka prête et reçoit les retours de livres, du lundi au samedi de 9 h à 17 h.',
                'etat_mis_a_jour_le' => $maintenant,
            ]);

        DB::table('services')
            ->where('nom', 'Urbanisme')
            ->whereNull('indisponible_depuis')
            ->whereNull('perturbe_depuis')
            ->whereNotExists($interruptionEnCours)
            ->update([
                'perturbe_depuis' => $maintenant,
                'motif_indisponibilite' => 'Forte affluence : délais d\'instruction des permis allongés (environ 3 semaines).',
                'retour_prevu_le' => null,
                'alternative_texte' => 'Déposez votre dossier en ligne : il est enregistré sans passer au guichet.',
                'etat_mis_a_jour_le' => $maintenant,
            ]);
    }

    public function down(): void
    {
        // Rien à défaire : l'état est ensuite tenu à jour par les agents (fiche du service).
    }
};
