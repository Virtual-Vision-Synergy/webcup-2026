<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soutiens d'habitants à une idée (F68), même mécanisme que F52. Nouvelle table : migration additive.
 * La contrainte unique garantit un seul soutien par habitant et par idée, même en cas de double clic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idea_supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'idea_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idea_supports');
    }
};
