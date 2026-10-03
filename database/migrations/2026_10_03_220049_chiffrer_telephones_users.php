<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * F69 : migration de DONNÉES (aucun changement de schéma), par lots de 200.
 * Chiffre les téléphones existants dans « telephone_chiffre », calcule leur empreinte, puis vide l'ancienne colonne.
 * Réversible : down() remet les numéros en clair dans « telephone ».
 * Dépend d'APP_KEY : ne jamais changer la clé sans la garder dans APP_PREVIOUS_KEYS (voir docs/SECURITY.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('telephone')->orderBy('id')->chunkById(200, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update([
                    'telephone_chiffre' => Crypt::encryptString($user->telephone),
                    'telephone_hash' => User::hashTelephone($user->telephone),
                    'telephone' => null,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('users')->whereNotNull('telephone_chiffre')->orderBy('id')->chunkById(200, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update([
                    'telephone' => Crypt::decryptString($user->telephone_chiffre),
                    'telephone_chiffre' => null,
                    'telephone_hash' => null,
                ]);
            }
        });
    }
};
