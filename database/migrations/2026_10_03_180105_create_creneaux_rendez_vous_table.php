<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F39 : créneaux de rendez-vous (un créneau = une place). Heures stockées en UTC.
 * rendez_vous_id unique : dernier rempart contre la double réservation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creneaux_rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->dateTime('debut');
            $table->dateTime('fin');
            $table->unsignedBigInteger('rendez_vous_id')->nullable()->unique();
            $table->timestamps();

            $table->unique(['service_id', 'debut']);
            $table->index('debut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creneaux_rendez_vous');
    }
};
