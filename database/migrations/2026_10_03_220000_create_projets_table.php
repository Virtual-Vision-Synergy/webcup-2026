<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quartier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('titre');
            $table->string('categorie', 20)->default('autre');
            $table->string('resume', 300);
            $table->text('description');
            $table->string('etat', 20)->default('etude')->index();
            $table->text('etapes')->nullable();
            $table->unsignedTinyInteger('etapes_terminees')->default(0);
            $table->unsignedTinyInteger('avancement')->default(0);
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->unsignedBigInteger('budget')->nullable();
            $table->string('lieu')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projets');
    }
};
