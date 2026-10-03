<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ligne_transports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('numero', 20);
            $table->string('nom');
            $table->string('mode', 20)->default('bus');
            $table->text('arrets');
            $table->text('horaires');
            $table->string('frequence')->nullable();
            $table->string('etat', 20)->default('normal')->index();
            $table->text('perturbation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ligne_transports');
    }
};
