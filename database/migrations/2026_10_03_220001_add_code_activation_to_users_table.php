<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F71 : empreinte du code d'activation à usage unique remis par un agent (jamais le code en clair).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('code_activation', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('code_activation');
        });
    }
};
