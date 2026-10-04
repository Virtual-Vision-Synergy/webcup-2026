<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F27 : traductions des fiches de services et de leurs informations de démarche (pièces à apporter, lieu du rendez-vous).
 * Nouvelle table : migration additive. Le français reste la référence dans la table services ; chaque champ est
 * facultatif (repli sur le français). Une seule traduction par service et par langue (contrainte unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('nom')->nullable();
            $table->text('description')->nullable();
            $table->text('horaires')->nullable();
            $table->string('adresse')->nullable();
            $table->string('lieu_rendez_vous')->nullable();
            $table->text('pieces_a_fournir')->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_translations');
    }
};
