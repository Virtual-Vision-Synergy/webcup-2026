<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réponses des habitants aux consultations (F65). Nouvelle table : migration additive.
 * La contrainte unique garantit une seule réponse par habitant et par consultation, même en cas de double clic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participations_consultation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('choix');
            $table->timestamps();

            $table->unique(['consultation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participations_consultation');
    }
};
