<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motif affiché aux habitants quand le service est indisponible (F63).
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('motif_indisponibilite')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('motif_indisponibilite');
        });
    }
};
