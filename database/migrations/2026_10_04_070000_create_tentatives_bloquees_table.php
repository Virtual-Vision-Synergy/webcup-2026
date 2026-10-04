<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F81 : journal des envois de formulaires bloqués (robots, envois trop rapides, trop d'envois).
 * Migration additive : une nouvelle table. Aucune donnée saisie n'est conservée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tentatives_bloquees', function (Blueprint $table) {
            $table->id();
            $table->string('formulaire', 30)->index();
            $table->string('motif', 20)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->nullable()->index();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tentatives_bloquees');
    }
};
