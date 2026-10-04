<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertes_canicule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('secteur');
            $table->string('niveau')->default('vigilance');
            $table->unsignedSmallInteger('temperature_max');
            $table->date('debut');
            $table->date('fin')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['debut', 'fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes_canicule');
    }
};
