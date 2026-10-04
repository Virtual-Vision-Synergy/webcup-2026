<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * F99 : rôle « partenaire », inséré ici (et non par un seeder) : la production ne lance jamais db:seed.
     */
    public function up(): void
    {
        if (DB::table('roles')->where('code', 'partenaire')->doesntExist()) {
            DB::table('roles')->insert([
                'code' => 'partenaire',
                'label' => 'Partenaire',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('code', 'partenaire')->value('id');

        if ($roleId !== null) {
            DB::table('users')->where('role_id', $roleId)->update(['role_id' => 1]);
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
