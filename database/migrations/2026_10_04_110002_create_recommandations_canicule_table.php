<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F31 : recommandations écrites par profil × niveau (sans IA), éditables par l'admin.
     */
    public function up(): void
    {
        Schema::create('recommandations_canicule', function (Blueprint $table) {
            $table->id();
            $table->string('profil', 40);
            $table->string('niveau', 20);
            $table->text('conseils');
            $table->timestamps();

            $table->unique(['profil', 'niveau']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommandations_canicule');
    }
};
