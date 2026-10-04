<?php

use Database\Seeders\AlertePanneElectriqueSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Données uniquement (F101) : met en ligne l'alerte « Panne électrique — secteur nord ».
     * Les seeders ne tournent jamais en production : c'est cette migration qui la publie au déploiement.
     * Aucune notification n'est envoyée ici (pas d'envoi d'e-mails pendant un déploiement).
     * Sans compte agent ou admin (base neuve, CI), rien n'est créé. Idempotent.
     */
    public function up(): void
    {
        AlertePanneElectriqueSeeder::publierAlerte();
    }

    public function down(): void
    {
        // Rien à défaire : l'alerte peut être marquée « Rétabli », dépubliée ou supprimée depuis l'espace agent.
    }
};
