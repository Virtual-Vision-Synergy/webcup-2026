<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F88 : préréglages d'export des demandes (« Rapport hebdo transports »). Nouvelle table : migration additive.
 * filters et columns sont validés contre la liste blanche d'ExportDemarches avant d'être enregistrés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('filters');
            $table->json('columns');
            $table->string('format', 10)->default('csv');
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_presets');
    }
};
