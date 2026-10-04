<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F104 : heure de début estimée de la perturbation annoncée (ex. tempête solaire), pour le compte à rebours.
     * Null pour les messages sans échéance.
     */
    public function up(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->timestamp('impact_prevu_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->dropColumn('impact_prevu_le');
        });
    }
};
