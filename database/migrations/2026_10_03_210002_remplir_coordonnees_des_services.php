<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Données uniquement (F45) : place sur la carte les services de l'annuaire déjà publiés.
 * Ne touche qu'aux services pas encore localisés.
 */
return new class extends Migration
{
    /** @var array<string, array{0: float, 1: float}> */
    private const COORDONNEES = [
        'État civil' => [-18.9102, 47.5256],
        'Accueil de la Mairie' => [-18.9098, 47.5252],
        'Élections et recensement' => [-18.9105, 47.5261],
        'Police municipale' => [-18.9093, 47.5264],
        'Urbanisme' => [-18.9061, 47.5218],
        'Services techniques et voirie' => [-18.8987, 47.5332],
        'Action sociale (CCAS)' => [-18.9178, 47.5297],
        'Santé publique' => [-18.9189, 47.5312],
        'Médiathèque Ravinala' => [-18.9037, 47.5281],
        'Petite enfance et écoles' => [-18.9141, 47.5187],
        'Sports et associations' => [-18.9213, 47.5175],
        'Environnement et propreté' => [-18.8962, 47.5145],
        'Culture et festivités' => [-18.9071, 47.5303],
        'Marchés et commerce' => [-18.9119, 47.5236],
        'Cimetière et affaires funéraires' => [-18.9265, 47.5389],
        'Jeunesse' => [-18.9012, 47.5241],
    ];

    public function up(): void
    {
        foreach (self::COORDONNEES as $nom => [$latitude, $longitude]) {
            DB::table('services')->where('nom', $nom)->whereNull('latitude')
                ->update(['latitude' => $latitude, 'longitude' => $longitude]);
        }
    }

    public function down(): void
    {
        // Rien à défaire : les colonnes sont supprimées par les migrations précédentes.
    }
};
