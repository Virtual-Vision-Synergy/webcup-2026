<?php

namespace Database\Seeders;

use App\Models\Actualite;
use App\Models\Demarche;
use App\Models\Message;
use App\Models\Onboarding;
use App\Models\Service;
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
     *   - user@example.com        Citoyen (prise en main terminée)
     *   - nouveau@example.com     Citoyen tout neuf (prise en main jamais vue → /bienvenue)
     *   - parcours@example.com    Citoyen à mi-parcours (profil complet, 1/3)
     *   - passe@example.com       Citoyen ayant passé la prise en main
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

            $citoyenDemo = User::factory()->profilComplet()->create([
                'name' => 'Citoyen Démo',
                'email' => 'user@example.com',
            ]);
            // Compte de démo historique : parcours de prise en main déjà terminé (pas de redirection vers /bienvenue).
            Onboarding::factory()->termine()->for($citoyenDemo)->create();

            // Parcours de prise en main (D12) : un nouvel habitant, un à mi-parcours, un qui l'a passé.
            User::factory()->create([
                'name' => 'Fanja Nouvelle',
                'email' => 'nouveau@example.com',
            ]);

            User::factory()->profilComplet()->create([
                'name' => 'Tahina Enchemin',
                'email' => 'parcours@example.com',
            ]);

            $citoyenPasse = User::factory()->create([
                'name' => 'Mialy Pressée',
                'email' => 'passe@example.com',
            ]);
            Onboarding::factory()->passe()->for($citoyenPasse)->create();
        }

        User::factory(8)->create([
            'password' => Str::password(32),
        ]);

        // Les comptes de démo du parcours de prise en main ne reçoivent pas de démarches aléatoires (sinon l'étape 3 serait faite).
        $users = User::query()->whereNotIn('email', ['nouveau@example.com', 'parcours@example.com', 'passe@example.com'])->get();

        $this->call(ServiceSeeder::class);

        Actualite::factory(20)->recycle($users)->create();

        Message::factory(20)->recycle($users)->create();

        $services = Service::all();
        Demarche::factory(20)->recycle($users)->recycle($services)->create();

        if (! app()->isProduction()) {
            // Quelques démarches pour le compte citoyen de démo : son espace personnel n'est pas vide.
            Demarche::factory(4)->recycle($services)->for(User::where('email', 'user@example.com')->firstOrFail())->create();
        }

        // make:feature:seeders
    }
}
