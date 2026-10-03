<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F38 : interruptions d'un service municipal (maintenance ou incident). Une ligne par interruption, l'historique est conservé.
 * Le service est indisponible tant qu'une interruption commencée (debut_at passé) n'est pas rétablie (retabli_at vide).
 * Heures stockées en UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_interruptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('motif');
            $table->text('alternative');
            $table->foreignId('alternative_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->dateTime('debut_at');
            $table->dateTime('retour_prevu_at')->nullable();
            $table->dateTime('retabli_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('retabli_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['service_id', 'retabli_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_interruptions');
    }
};
