<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F54 : appareils connus de chaque utilisateur (alerte de connexion depuis un nouvel appareil).
 * Minimisation : ni IP complète ni user-agent brut, seulement des familles et une IP masquée.
 * Migration additive : une nouvelle table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('known_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_token_hash', 64)->index();
            $table->string('browser', 60);
            $table->string('os', 60);
            $table->string('device_type', 20);
            $table->string('ip_approx', 64)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('known_devices');
    }
};
