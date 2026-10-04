<?php

use Database\Seeders\RecommandationsCaniculeSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * F31 : recommandations de départ de l'Agence sanitaire (données uniquement, aucun changement de schéma). Idempotent.
     */
    public function up(): void
    {
        (new RecommandationsCaniculeSeeder)->run();
    }

    public function down(): void
    {
        // Données conservées : l'admin a pu les modifier depuis.
    }
};
