<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soutiens d'habitants à un signalement déposé par un autre (F52). Nouvelle table : migration additive.
 * La contrainte unique garantit un seul soutien par habitant et par demande, même en cas de double clic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soutiens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signalement_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'signalement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soutiens');
    }
};
