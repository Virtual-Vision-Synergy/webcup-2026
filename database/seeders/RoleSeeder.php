<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Les 3 rôles de base. Ils sont déjà créés par la migration create_roles_table :
 * ce seeder est idempotent et remet seulement les libellés à jour.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            Role::CITOYEN => 'Citoyen',
            Role::AGENT => 'Agent municipal',
            Role::ADMIN => 'Administrateur',
        ] as $code => $label) {
            Role::updateOrCreate(['code' => $code], ['label' => $label]);
        }
    }
}
