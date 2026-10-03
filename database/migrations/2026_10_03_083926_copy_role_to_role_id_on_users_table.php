<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Données uniquement : les admins existants (ancienne colonne role = 'admin') gardent leur rôle.
     */
    public function up(): void
    {
        $adminId = DB::table('roles')->where('code', 'admin')->value('id');

        DB::table('users')->where('role', 'admin')->update(['role_id' => $adminId]);
    }

    public function down(): void
    {
        // Rien à défaire : l'ancienne colonne role n'a pas été modifiée.
    }
};
