<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consignes à suivre (F29), une par ligne.
     */
    public function up(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->text('consignes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->dropColumn('consignes');
        });
    }
};
