<?php

use Database\Seeders\LangageClairSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * F89 : versions en langage clair des services principaux en production (données uniquement, aucun changement de schéma).
     * Idempotent : un service qui a déjà une version en langage clair n'est pas modifié.
     */
    public function up(): void
    {
        (new LangageClairSeeder)->run();
    }

    public function down(): void
    {
        // Données conservées : un agent a pu les modifier depuis.
    }
};
