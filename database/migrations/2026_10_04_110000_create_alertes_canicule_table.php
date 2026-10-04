<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F31 : alertes canicule déclenchées par l'Agence sanitaire (niveau, période, message), ciblées par quartier.
     */
    public function up(): void
    {
        Schema::create('alertes_canicule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('niveau', 20);
            $table->unsignedTinyInteger('temperature_max')->nullable();
            $table->dateTime('debut');
            $table->dateTime('fin');
            $table->text('message');
            $table->dateTime('notified_at')->nullable();
            $table->timestamps();

            $table->index(['debut', 'fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes_canicule');
    }
};
