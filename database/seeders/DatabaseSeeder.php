<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Données de démonstration.
     *
     * En local, un compte par rôle (mot de passe : password) :
     *   - admin@example.com       Administrateur
     *   - agent@example.com       Agent municipal
     *   - jury.agent@example.com  Agent municipal (compte jury agent)
     *   - user@example.com        Citoyen
     * En production : aucun compte avec un mot de passe connu n'est créé ;
     * les comptes jury sont créés à la main (/register) puis passés agent ou admin dans /admin/users.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        if (! app()->isProduction()) {
            User::factory()->admin()->create([
                'name' => 'Admin Démo',
                'email' => 'admin@example.com',
            ]);

            User::factory()->agent()->create([
                'name' => 'Agent Démo',
                'email' => 'agent@example.com',
            ]);

            User::factory()->agent()->create([
                'name' => 'Jury Agent',
                'email' => 'jury.agent@example.com',
            ]);

            User::factory()->create([
                'name' => 'Citoyen Démo',
                'email' => 'user@example.com',
            ]);
        }

        User::factory(8)->create([
            'password' => Str::password(32),
        ]);

        $users = User::all();

        // make:feature:seeders
    }
}
