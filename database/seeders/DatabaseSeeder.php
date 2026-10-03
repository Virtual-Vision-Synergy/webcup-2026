<?php

namespace Database\Seeders;

use App\Models\Actualite;
use App\Models\Demarche;
use App\Models\Message;
use App\Models\Service;
use App\Models\Signalement;
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

        $this->call(ServiceSeeder::class);

        Actualite::factory(20)->recycle($users)->create();

        Message::factory(20)->recycle($users)->create();

        $services = Service::all();
        Demarche::factory(20)->recycle($users)->recycle($services)->create();

        if (! app()->isProduction()) {
            // Quelques démarches pour le compte citoyen de démo : son espace personnel n'est pas vide.
            Demarche::factory(4)->recycle($services)->for(User::where('email', 'user@example.com')->firstOrFail())->create();
        }

        Signalement::factory(15)->recycle($users)->create();
        Signalement::factory(5)->nouveau()->recycle($users)->create();

        if (! app()->isProduction()) {
            // Signalements du compte citoyen de démo, dont un lampadaire cassé tout juste signalé.
            $citoyen = User::where('email', 'user@example.com')->firstOrFail();
            Signalement::factory()->nouveau()->for($citoyen)->create([
                'categorie' => 'eclairage',
                'description' => 'Le lampadaire devant chez moi est cassé, la rue est plongée dans le noir depuis trois jours.',
                'lieu' => 'Rue des Lumières, devant le n° 12',
            ]);
            Signalement::factory(2)->for($citoyen)->create();
        }

        // make:feature:seeders
    }
}
