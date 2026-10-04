<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * F83 : référence des démarches déjà déposées (données uniquement, aucun changement de schéma).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('demarches')->whereNull('reference')->select(['id', 'created_at'])->chunkById(500, function ($demarches): void {
            foreach ($demarches as $demarche) {
                $annee = $demarche->created_at !== null ? Carbon::parse($demarche->created_at)->year : (int) now()->year;

                DB::table('demarches')->where('id', $demarche->id)->update([
                    // Même format que Demarche::formaterReference(), recopié pour que la migration ne dépende pas du modèle.
                    'reference' => sprintf('NT-%d-%06d', $annee, $demarche->id),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Rien à défaire : la colonne est retirée par la migration précédente.
    }
};
