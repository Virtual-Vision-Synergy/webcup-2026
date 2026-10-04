<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F78 : les actualités sont triées par date (page d'accueil, liste des actualités).
 * Migration additive : un seul index ajouté, aucune donnée ni colonne modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actualites', function (Blueprint $table) {
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::table('actualites', function (Blueprint $table) {
            $table->dropIndex(['date']);
        });
    }
};
