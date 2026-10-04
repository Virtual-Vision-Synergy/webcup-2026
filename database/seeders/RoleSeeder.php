<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Les rôles de base. Ils sont déjà créés par les migrations create_roles_table et add_partenaire_role (F99) :
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
            Role::PARTENAIRE => 'Partenaire',
        ] as $code => $label) {
            Role::updateOrCreate(['code' => $code], ['label' => $label]);
        }
    }
}
