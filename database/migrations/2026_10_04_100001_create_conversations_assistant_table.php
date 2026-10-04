<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F91 : échanges anonymisés avec l'assistant (ni utilisateur, ni IP), consultés par l'admin pour améliorer les réponses.
     */
    public function up(): void
    {
        Schema::create('conversations_assistant', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation')->index();
            $table->string('message', 300);
            $table->string('type', 20)->index();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations_assistant');
    }
};
