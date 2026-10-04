<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Données uniquement (F39) : ouvre la prise de rendez-vous pour les services de l'annuaire déjà publiés
 * (lieu, durée, pièces à apporter). Ne touche qu'aux services qui ne prennent pas encore de rendez-vous.
 * Les créneaux se génèrent ensuite avec `php artisan appointments:generate-slots`.
 */
return new class extends Migration
{
    /** @var array<string, array{lieu_rendez_vous: string, duree_rendez_vous: int, pieces_a_fournir: string}> */
    private const SERVICES = [
        'État civil' => [
            'lieu_rendez_vous' => 'Hôtel de ville, rez-de-chaussée, guichet 2 (état civil)',
            'duree_rendez_vous' => 30,
            'pieces_a_fournir' => "Pièce d'identité en cours de validité\nLivret de famille (si vous en avez un)\nJustificatif de domicile de moins de 3 mois",
        ],
        'Accueil de la Mairie' => [
            'lieu_rendez_vous' => 'Hôtel de ville, hall d’accueil, bureau 1',
            'duree_rendez_vous' => 15,
            'pieces_a_fournir' => "Pièce d'identité en cours de validité\nTout courrier ou document lié à votre demande",
        ],
        'Urbanisme' => [
            'lieu_rendez_vous' => 'Centre administratif, 2e étage, bureau 204 (service urbanisme)',
            'duree_rendez_vous' => 45,
            'pieces_a_fournir' => "Pièce d'identité en cours de validité\nPlan de situation du terrain\nTitre de propriété ou autorisation du propriétaire\nCroquis ou plans du projet",
        ],
        'Action sociale (CCAS)' => [
            'lieu_rendez_vous' => 'Maison des solidarités, 8 rue des Baobabs, accueil du CCAS',
            'duree_rendez_vous' => 30,
            'pieces_a_fournir' => "Pièce d'identité en cours de validité\nJustificatif de domicile de moins de 3 mois\nJustificatifs de ressources des 3 derniers mois\nLivret de famille (si vous en avez un)",
        ],
    ];

    public function up(): void
    {
        foreach (self::SERVICES as $nom => $infos) {
            DB::table('services')->where('nom', $nom)->whereNull('duree_rendez_vous')->update($infos);
        }
    }

    public function down(): void
    {
        // Rien à défaire : les colonnes sont supprimées par les migrations précédentes.
    }
};
