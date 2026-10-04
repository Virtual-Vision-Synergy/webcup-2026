<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F97 : trajet habituel d'un habitant (ligne + arrêt), pour être prévenu quand la ligne est interrompue.
     */
    public function up(): void
    {
        Schema::create('abonnements_ligne', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ligne_transport_id')->constrained()->cascadeOnDelete();
            $table->string('arret')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'ligne_transport_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnements_ligne');
    }
};
