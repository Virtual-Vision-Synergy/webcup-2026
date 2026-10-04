<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F78 : les démarches sont triées et filtrées par date de dépôt (tableaux de bord habitant et agent, « aujourd'hui », 7 derniers jours).
 * Migration additive : un seul index ajouté, aucune donnée ni colonne modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('demarches', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
