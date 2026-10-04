<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F89 : date de validation de la version en langage clair du message ; null = brouillon non publié.
     */
    public function up(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->timestamp('langage_clair_valide_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->dropColumn('langage_clair_valide_le');
        });
    }
};
