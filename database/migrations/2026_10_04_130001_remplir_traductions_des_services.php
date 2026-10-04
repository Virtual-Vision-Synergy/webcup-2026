<?php

use Database\Seeders\ServiceTranslationSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Données uniquement (F27) : versions anglaises de quatre fiches de l'annuaire, comme ServiceTranslationSeeder en local.
 * N'ajoute que les traductions absentes : une traduction saisie par un agent n'est jamais écrasée.
 */
return new class extends Migration
{
    public function up(): void
    {
        ServiceTranslationSeeder::remplir();
    }

    public function down(): void
    {
        // Rien à défaire : la table est supprimée par la migration précédente ; les traductions sont ensuite tenues par les agents.
    }
};
