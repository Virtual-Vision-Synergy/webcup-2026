<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F72 « Par où commencer ? » : situation déclarée par l'habitant (logement, famille, emploi, santé),
 * pour lui recommander des services. Colonne additive et facultative (null = pas encore répondu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboardings', function (Blueprint $table) {
            $table->json('situation')->nullable()->after('skipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('onboardings', function (Blueprint $table) {
            $table->dropColumn('situation');
        });
    }
};
