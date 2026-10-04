<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * D10 : mots-clés et synonymes par service (« poubelle » → Environnement et propreté), éditables par l'admin.
     */
    public function up(): void
    {
        Schema::create('mots_cles_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('mot', 100);
            $table->timestamps();

            $table->unique(['service_id', 'mot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mots_cles_service');
    }
};
