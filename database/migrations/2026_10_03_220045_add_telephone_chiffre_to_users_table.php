<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F69 : téléphone chiffré en base (cast « encrypted »). Colonne additive : l'ancienne colonne « telephone »
 * est conservée (règle des migrations en production) mais vidée par la migration de données suivante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('telephone_chiffre')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('telephone_chiffre');
        });
    }
};
