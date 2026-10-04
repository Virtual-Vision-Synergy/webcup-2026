<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Données uniquement (F28) : marque comme prioritaires les démarches les plus courantes de l'annuaire,
 * comme le fait ServiceSeeder en local. Ne fait rien si un agent a déjà choisi des services mis en avant.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const PRIORITAIRES = ['État civil', 'Accueil de la Mairie', 'Urbanisme', 'Action sociale (CCAS)'];

    public function up(): void
    {
        if (DB::table('services')->where('mis_en_avant', true)->exists()) {
            return;
        }

        DB::table('services')->whereIn('nom', self::PRIORITAIRES)->update(['mis_en_avant' => true]);
    }

    public function down(): void
    {
        // Rien à défaire : choix éditorial modifiable par les agents (étoile sur /services).
    }
};
