<?php

use Database\Seeders\ReglesAssistantSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * F91 : règles de départ de l'assistant (données uniquement, aucun changement de schéma). Idempotent.
     */
    public function up(): void
    {
        (new ReglesAssistantSeeder)->run();
    }

    public function down(): void
    {
        // Données conservées : l'admin a pu les modifier depuis.
    }
};
