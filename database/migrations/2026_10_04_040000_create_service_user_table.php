<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| F70 : services couverts par chaque agent (un agent peut en couvrir plusieurs ; les citoyens n'y figurent jamais).
| Affectation réservée à l'admin (UserPolicy::assignServices), jamais par assignation de masse.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_user');
    }
};
