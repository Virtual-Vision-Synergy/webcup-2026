<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F85 : anomalies repérées par le contrôle d'intégrité (statut impossible, référence orpheline, date future, doublon).
 * Migration additive : une nouvelle table. « signature » rend chaque anomalie unique (pas de doublon d'un passage à l'autre).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anomalies_donnees', function (Blueprint $table) {
            $table->id();
            $table->string('signature', 120)->unique();
            $table->string('type', 30)->index();
            $table->string('table_concernee', 60);
            $table->unsignedBigInteger('enregistrement_id')->nullable();
            $table->string('description');
            $table->timestamp('detectee_le')->nullable();
            $table->timestamp('resolue_le')->nullable()->index();
            $table->foreignId('resolue_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomalies_donnees');
    }
};
