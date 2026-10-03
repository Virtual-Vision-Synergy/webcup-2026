<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F39 : rendez-vous d'un habitant. Le créneau garde l'heure (UTC) ; une annulation libère le créneau
 * mais le rendez-vous conserve creneau_id pour l'historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creneau_id')->constrained('creneaux_rendez_vous')->restrictOnDelete();
            $table->text('motif')->nullable();
            $table->string('statut', 50)->default('confirme')->index();
            $table->dateTime('annule_le')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendez_vous');
    }
};
