<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Données uniquement : classe les services déjà publiés (annuaire de la Mairie) dans leur catégorie.
 * Ne touche qu'aux services sans catégorie.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const CATEGORIES = [
        'État civil' => 'administratif',
        'Accueil de la Mairie' => 'administratif',
        'Élections et recensement' => 'administratif',
        'Cimetière et affaires funéraires' => 'administratif',
        'Urbanisme' => 'urbanisme',
        'Services techniques et voirie' => 'urbanisme',
        'Environnement et propreté' => 'urbanisme',
        'Santé publique' => 'sante',
        'Action sociale (CCAS)' => 'social',
        'Petite enfance et écoles' => 'education',
        'Jeunesse' => 'education',
        'Médiathèque Ravinala' => 'culture',
        'Sports et associations' => 'culture',
        'Culture et festivités' => 'culture',
        'Police municipale' => 'securite',
        'Marchés et commerce' => 'economie',
    ];

    public function up(): void
    {
        foreach (self::CATEGORIES as $nom => $categorie) {
            DB::table('services')->where('nom', $nom)->whereNull('categorie')->update(['categorie' => $categorie]);
        }
    }

    public function down(): void
    {
        // Rien à défaire : la colonne est supprimée par la migration précédente.
    }
};
