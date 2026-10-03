<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traductions', function (Blueprint $table) {
            $table->id();
            $table->morphs('traduisible');
            $table->string('locale', 5);
            $table->string('titre')->nullable();
            $table->text('description')->nullable();
            $table->text('horaires')->nullable();
            $table->timestamps();

            $table->unique(['traduisible_type', 'traduisible_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traductions');
    }
};
