<?php

use Database\Seeders\ExplicationsSimplesSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * F90 : explications et synonymes de départ en production (données uniquement, aucun changement de schéma).
     * Idempotent : une clé ou un mot déjà présent n'est pas modifié.
     */
    public function up(): void
    {
        (new ExplicationsSimplesSeeder)->run();
    }

    public function down(): void
    {
        // Données conservées : l'admin a pu les modifier depuis.
    }
};
