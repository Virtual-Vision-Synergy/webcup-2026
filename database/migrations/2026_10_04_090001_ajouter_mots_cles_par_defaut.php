<?php

use Database\Seeders\MotsClesServiceSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * D10 : synonymes de départ pour les services déjà en ligne (données uniquement, aucun changement de schéma).
     * Idempotent ; sur une base neuve sans services, rien n'est inséré (DatabaseSeeder s'en charge en local).
     */
    public function up(): void
    {
        (new MotsClesServiceSeeder)->run();
    }

    public function down(): void
    {
        // Données conservées : l'admin a pu les modifier depuis.
    }
};
