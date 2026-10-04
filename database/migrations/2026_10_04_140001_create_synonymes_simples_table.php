<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F90 : règles « mot administratif → mot simple » (« justificatif » → « preuve »), éditables par l'admin.
     */
    public function up(): void
    {
        Schema::create('synonymes_simples', function (Blueprint $table) {
            $table->id();
            $table->string('mot', 100)->unique();
            $table->string('equivalent');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synonymes_simples');
    }
};
