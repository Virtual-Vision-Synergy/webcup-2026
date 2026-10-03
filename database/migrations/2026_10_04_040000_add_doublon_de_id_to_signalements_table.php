<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F75 : un signalement fusionné pointe vers la demande principale de son groupe.
 * Migration additive : une seule colonne nullable, aucune contrainte sur l'existant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signalements', function (Blueprint $table) {
            $table->unsignedBigInteger('doublon_de_id')->nullable()->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('signalements', function (Blueprint $table) {
            $table->dropColumn('doublon_de_id');
        });
    }
};
