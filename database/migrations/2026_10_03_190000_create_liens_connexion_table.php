<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * D02 : liens de connexion sans mot de passe. Seule l'empreinte SHA-256 du jeton est stockée ;
     * used_at rend le lien inutilisable après un premier usage.
     */
    public function up(): void
    {
        Schema::create('liens_connexion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liens_connexion');
    }
};
