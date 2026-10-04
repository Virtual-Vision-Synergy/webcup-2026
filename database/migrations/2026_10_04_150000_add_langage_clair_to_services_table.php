<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F89 : version en langage clair du service, rédigée par un agent ou un administrateur.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->text('langage_clair')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('langage_clair');
        });
    }
};
