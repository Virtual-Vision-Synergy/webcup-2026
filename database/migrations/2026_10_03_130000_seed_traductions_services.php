<?php

use Database\Seeders\ServiceSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Traductions de démonstration des services : en production les seeders ne tournent pas,
 * donc elles sont posées par migration (idempotent, ignore les services absents).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ServiceSeeder)->traduire();
    }

    public function down(): void
    {
        // Les traductions sont des données : on ne les supprime pas.
    }
};
