<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F97 : lignes concernées par une interruption.
     */
    public function up(): void
    {
        Schema::create('interruption_transport_ligne', function (Blueprint $table) {
            $table->foreignId('interruption_transport_id')->constrained('interruptions_transport')->cascadeOnDelete();
            $table->foreignId('ligne_transport_id')->constrained()->cascadeOnDelete();
            $table->primary(['interruption_transport_id', 'ligne_transport_id'], 'interruption_ligne_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interruption_transport_ligne');
    }
};
