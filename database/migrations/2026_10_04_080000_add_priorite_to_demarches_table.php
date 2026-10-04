<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F80 : priorité de la demande (basse, normale, haute, urgente), suggérée automatiquement ou ajustée par un agent.
     */
    public function up(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->string('priorite', 10)->default('normale')->index();
        });
    }

    public function down(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->dropIndex(['priorite']);
            $table->dropColumn('priorite');
        });
    }
};
