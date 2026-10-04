<?php

use Database\Seeders\AlerteMonteeDesEauxSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Données uniquement (F29) : met en ligne l'alerte « Montée des eaux — quartier sud ».
     * Les seeders ne tournent jamais en production : c'est cette migration qui la publie au déploiement.
     * Sans compte agent ou admin (base neuve, CI), rien n'est créé. Idempotent.
     */
    public function up(): void
    {
        AlerteMonteeDesEauxSeeder::publierAlerte();
    }

    public function down(): void
    {
        // Rien à défaire : l'alerte peut être dépubliée ou supprimée depuis l'espace agent.
    }
};
