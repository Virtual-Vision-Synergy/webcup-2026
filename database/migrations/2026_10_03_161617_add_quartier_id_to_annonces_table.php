<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quartier ciblé par une alerte (F29). Null = toute la ville (messages généraux de D18).
     */
    public function up(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->foreignId('quartier_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quartier_id');
        });
    }
};
