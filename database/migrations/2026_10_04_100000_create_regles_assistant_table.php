<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F91 : règles de l'assistant d'orientation (« mot de passe oublié » → réponse + lien), éditables par l'admin.
     */
    public function up(): void
    {
        Schema::create('regles_assistant', function (Blueprint $table) {
            $table->id();
            $table->string('declencheurs', 500);
            $table->text('reponse');
            $table->string('lien_libelle', 100)->nullable();
            $table->string('lien_url', 255)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regles_assistant');
    }
};
