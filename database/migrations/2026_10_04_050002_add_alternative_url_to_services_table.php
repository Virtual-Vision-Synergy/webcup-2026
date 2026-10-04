<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F64 : lien de l'alternative proposée (http ou https uniquement).
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('alternative_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('alternative_url');
        });
    }
};
