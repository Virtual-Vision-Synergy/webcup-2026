<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étapes datées du suivi d'une démarche (D11) : une ligne à chaque changement d'état par un agent.
 * Nouvelle table : migration additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demarche_etapes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demarche_id')->constrained()->cascadeOnDelete();
            $table->string('statut');
            $table->text('commentaire')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['demarche_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demarche_etapes');
    }
};
