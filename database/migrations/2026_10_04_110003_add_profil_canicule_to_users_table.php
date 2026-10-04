<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F31 : profil choisi par l'habitant pour ses conseils canicule (facultatif, jamais assignable en masse).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profil_canicule', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profil_canicule');
        });
    }
};
