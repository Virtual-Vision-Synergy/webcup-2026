<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avis des habitants sur les projets de la ville (F66). Nouvelle table : migration additive.
 * La contrainte unique garantit un seul avis par habitant et par projet, même en cas de double clic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avis_projets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('projet_id')->constrained()->cascadeOnDelete();
            $table->string('position', 20);
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'projet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avis_projets');
    }
};
