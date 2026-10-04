<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F97 : interruption d'une ou plusieurs lignes de transport, avec les solutions de remplacement saisies par l'agent.
     */
    public function up(): void
    {
        Schema::create('interruptions_transport', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('cause');
            $table->text('arrets_touches')->nullable();
            $table->dateTime('debut')->index();
            $table->dateTime('fin')->index();
            $table->json('solutions')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interruptions_transport');
    }
};
