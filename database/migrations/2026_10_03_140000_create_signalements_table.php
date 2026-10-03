<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signalements de problèmes dans l'espace public (F25). Nouvelle table (l'ancien exemple a été retiré
 * par 2026_10_02_090000_drop_signalements_table) : migration additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signalements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('categorie', 50)->index();
            $table->text('description');
            $table->string('lieu');
            $table->string('photo')->nullable();
            $table->string('statut', 50)->default('nouveau')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signalements');
    }
};
