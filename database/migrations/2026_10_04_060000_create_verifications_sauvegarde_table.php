<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F87 : historique des vérifications des sauvegardes de la base.
     */
    public function up(): void
    {
        Schema::create('verifications_sauvegarde', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fichier');
            $table->unsignedBigInteger('taille')->default(0);
            $table->timestamp('sauvegarde_le')->nullable();
            $table->string('statut', 20)->index();
            $table->unsignedInteger('nb_tables')->default(0);
            $table->unsignedInteger('nb_tables_base')->default(0);
            $table->string('rapport', 500);
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifications_sauvegarde');
    }
};
