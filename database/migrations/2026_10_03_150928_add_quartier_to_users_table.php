<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quartier de résidence de l'habitant (profil, étape 1 du parcours de prise en main D12).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('quartier', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('quartier');
        });
    }
};
