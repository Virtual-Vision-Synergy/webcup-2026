<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F90 : explication simple rédigée à l'avance pour un passage administratif, éditable par l'admin.
     */
    public function up(): void
    {
        Schema::create('explications_simples', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 80)->unique();
            $table->string('titre');
            $table->text('texte_officiel')->nullable();
            $table->text('explication');
            $table->json('termes')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('explications_simples');
    }
};
