<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Service désactivé par un administrateur (F63) : null = disponible.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->timestamp('indisponible_depuis')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('indisponible_depuis');
        });
    }
};
