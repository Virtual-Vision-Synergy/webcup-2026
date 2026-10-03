<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Message officiel du Haut Conseil (F73) : publié par un administrateur uniquement, affiché en tête de toutes les pages.
     */
    public function up(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->boolean('officiel')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->dropColumn('officiel');
        });
    }
};
