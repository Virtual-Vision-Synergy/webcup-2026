<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F78 : les signalements sont listés du plus récent au plus ancien (liste, tableau de bord agent).
 * Migration additive : un seul index ajouté, aucune donnée ni colonne modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signalements', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('signalements', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
