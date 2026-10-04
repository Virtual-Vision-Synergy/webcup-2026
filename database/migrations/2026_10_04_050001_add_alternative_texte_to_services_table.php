<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F64 : alternative proposée aux habitants pendant une interruption (texte).
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('alternative_texte')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('alternative_texte');
        });
    }
};
