<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F86 : date de prise en charge de l'urgence (traçabilité : quand).
     */
    public function up(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->timestamp('pris_en_charge_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->dropColumn('pris_en_charge_le');
        });
    }
};
