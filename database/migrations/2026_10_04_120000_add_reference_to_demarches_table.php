<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F83 : référence lisible de l'accusé de réception (NT-2026-000123). Colonne additive, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->string('reference', 20)->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->dropColumn('reference');
        });
    }
};
