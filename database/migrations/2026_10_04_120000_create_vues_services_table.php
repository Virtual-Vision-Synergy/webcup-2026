<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F98 : compteur anonymisé des consultations de fiches services (aucun user_id, aucune IP).
     * Une ligne par service, par quartier du visiteur (secteur, facultatif) et par jour.
     */
    public function up(): void
    {
        Schema::create('vues_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quartier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('jour');
            $table->unsignedInteger('nombre')->default(0);
            $table->timestamps();

            $table->index(['jour', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vues_services');
    }
};
