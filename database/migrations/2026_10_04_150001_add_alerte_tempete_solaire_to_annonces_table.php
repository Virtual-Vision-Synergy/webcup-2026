<?php

use Database\Seeders\AlerteTempeteSolaireSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Données uniquement (F104) : met en ligne l'alerte « Tempête solaire » sur toute la ville.
     * Les seeders ne tournent jamais en production : c'est cette migration qui la publie au déploiement.
     * Aucune notification n'est envoyée ici (pas d'envoi d'e-mails pendant un déploiement).
     * Sans compte agent ou admin (base neuve, CI), rien n'est créé. Idempotent.
     */
    public function up(): void
    {
        AlerteTempeteSolaireSeeder::publierAlerte();
    }

    public function down(): void
    {
        // Rien à défaire : l'alerte peut être dépubliée ou supprimée depuis l'espace agent.
    }
};
