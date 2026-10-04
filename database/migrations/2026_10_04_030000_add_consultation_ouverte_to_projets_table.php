<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F66 : projet ouvert (ou non) à l'avis des habitants. Colonne avec valeur par défaut : migration additive.
     */
    public function up(): void
    {
        Schema::table('projets', function (Blueprint $table) {
            $table->boolean('consultation_ouverte')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('projets', function (Blueprint $table) {
            $table->dropColumn('consultation_ouverte');
        });
    }
};
