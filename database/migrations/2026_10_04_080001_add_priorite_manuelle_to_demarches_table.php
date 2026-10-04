<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F80 : vrai quand un agent a fixé la priorité lui-même (la suggestion automatique ne la remplace plus).
     */
    public function up(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->boolean('priorite_manuelle')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->dropColumn('priorite_manuelle');
        });
    }
};
