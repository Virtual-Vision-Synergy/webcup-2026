<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F69 : empreinte HMAC du téléphone (index aveugle). Une colonne chiffrée n'est pas cherchable en SQL :
 * la connexion par téléphone (F71) et le contrôle d'unicité passent par cette empreinte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telephone_hash', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['telephone_hash']);
            $table->dropColumn('telephone_hash');
        });
    }
};
