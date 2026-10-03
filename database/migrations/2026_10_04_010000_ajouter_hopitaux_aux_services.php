<?php

use App\Models\Service;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Données uniquement (F46) : ajoute à l'annuaire les hôpitaux et services d'urgence de la ville (catégorie santé).
 * Idempotent : un établissement déjà présent (même nom) est ignoré. Sans aucun compte, rien n'est ajouté.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roleAdminId = DB::table('roles')->where('code', 'admin')->value('id');
        $auteurId = DB::table('users')->where('role_id', $roleAdminId)->value('id') ?? DB::table('users')->value('id');

        if ($auteurId === null) {
            return;
        }

        foreach (Service::ETABLISSEMENTS_SANTE as $etablissement) {
            if (DB::table('services')->where('nom', $etablissement['nom'])->exists()) {
                continue;
            }

            DB::table('services')->insert([
                ...$etablissement,
                'slug' => Service::uniqueSlug($etablissement['nom']),
                'categorie' => 'sante',
                'user_id' => $auteurId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('services')->whereIn('nom', array_column(Service::ETABLISSEMENTS_SANTE, 'nom'))->delete();
    }
};
