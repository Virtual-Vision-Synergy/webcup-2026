<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F89 : version en langage clair du message, rédigée par l'agent.
     */
    public function up(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->text('langage_clair')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->dropColumn('langage_clair');
        });
    }
};
