<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F86 : demande signalée comme urgence médicale (case cochée ou mots-clés détectés).
     */
    public function up(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->boolean('urgence_medicale')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->dropIndex(['urgence_medicale']);
            $table->dropColumn('urgence_medicale');
        });
    }
};
