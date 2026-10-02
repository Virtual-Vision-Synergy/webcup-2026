<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire l'exemple « Signalement » (issue #29). La migration d'origine reste intacte :
 * un déploiement fait tourner les migrations dans l'ordre, et down() recrée la table à l'identique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('signalements');
    }

    public function down(): void
    {
        Schema::create('signalements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('niveau', 50)->index();
            $table->string('zone');
            $table->string('photo')->nullable();
            $table->date('date_incident');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
    }
};
