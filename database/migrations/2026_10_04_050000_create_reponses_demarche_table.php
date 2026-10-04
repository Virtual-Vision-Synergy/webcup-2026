<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F84 : fil de messages entre les agents et l'habitant sur une démarche. Nouvelle table : migration additive.
 * user_id est nullable (nullOnDelete) : le fil reste lisible si le compte de l'auteur est supprimé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reponses_demarche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demarche_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('de_agent')->default(false);
            $table->text('message');
            $table->timestamps();

            $table->index(['demarche_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reponses_demarche');
    }
};
