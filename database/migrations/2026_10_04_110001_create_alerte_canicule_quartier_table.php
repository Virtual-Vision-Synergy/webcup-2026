<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F31 : quartiers (secteurs) touchés par une alerte canicule — une alerte peut en viser plusieurs.
     */
    public function up(): void
    {
        Schema::create('alerte_canicule_quartier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alerte_canicule_id')->constrained('alertes_canicule')->cascadeOnDelete();
            $table->foreignId('quartier_id')->constrained()->cascadeOnDelete();

            $table->unique(['alerte_canicule_id', 'quartier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerte_canicule_quartier');
    }
};
