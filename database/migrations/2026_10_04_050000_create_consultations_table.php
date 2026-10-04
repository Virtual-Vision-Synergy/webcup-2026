<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consultations des habitants (F65). Nouvelle table : migration additive.
 * Dates stockées en UTC (saisies en heure de Madagascar). quartier_id null = tous les habitants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quartier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question');
            $table->text('explication');
            $table->text('options');
            $table->dateTime('ouverture_le');
            $table->dateTime('cloture_le')->index();
            $table->text('decision')->nullable();
            $table->dateTime('decision_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
