<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Données uniquement (F29) : échelle de gravité information / vigilance / alerte / danger.
     * Les anciens niveaux de D18 sont convertis : important → vigilance, urgent → alerte.
     */
    public function up(): void
    {
        DB::table('annonces')->where('niveau', 'important')->update(['niveau' => 'vigilance']);
        DB::table('annonces')->where('niveau', 'urgent')->update(['niveau' => 'alerte']);
    }

    public function down(): void
    {
        DB::table('annonces')->where('niveau', 'vigilance')->update(['niveau' => 'important']);
        DB::table('annonces')->whereIn('niveau', ['alerte', 'danger'])->update(['niveau' => 'urgent']);
    }
};
