<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F86 : agent ou admin qui a pris en charge l'urgence (traçabilité : qui).
     */
    public function up(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->foreignId('pris_en_charge_par')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pris_en_charge_par');
        });
    }
};
