<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F71 : langue choisie par l'habitant, mémorisée d'une connexion à l'autre.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('langue', 5)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('langue');
        });
    }
};
