<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date de retour prévue du service indisponible, facultative (F63).
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->date('retour_prevu_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('retour_prevu_le');
        });
    }
};
