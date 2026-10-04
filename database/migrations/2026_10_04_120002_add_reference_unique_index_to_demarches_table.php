<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F83 : unicité de la référence garantie par la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->unique('reference');
        });
    }

    public function down(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->dropUnique(['reference']);
        });
    }
};
