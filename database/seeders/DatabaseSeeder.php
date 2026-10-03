<?php

namespace Database\Seeders;

use App\Models\Actualite;
use App\Models\Annonce;
use App\Models\Demarche;
use App\Models\Message;
use App\Models\Role;
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

        // Messages généraux (D18) : un en cours, un programmé, un expiré, publiés par un agent.
        $auteur = User::where('role_id', Role::idFor(Role::AGENT))->first() ?? $users->first();
        Annonce::factory()->active()->for($auteur)->create([
            'titre' => 'Coupure d’eau à Ambohijanahary',
            'contenu' => 'Travaux sur le réseau : l’eau sera coupée dans le quartier Ambohijanahary aujourd’hui de 9 h à 16 h. Faites une réserve et évitez les lessives ; un camion-citerne stationne place des Pionniers.',
            'niveau' => 'important',
        ]);
        Annonce::factory()->programmee()->for($auteur)->create([
            'titre' => 'Alerte météo : vents violents demain',
            'contenu' => 'Rafales jusqu’à 110 km/h attendues. Rentrez le mobilier extérieur, limitez vos déplacements et suivez les consignes du Haut Conseil sur cette plateforme.',
            'niveau' => 'urgent',
        ]);
        Annonce::factory()->expiree()->for($auteur)->create([
            'titre' => 'Fermeture exceptionnelle de la mairie annexe',
            'contenu' => 'La mairie annexe du secteur Nord était fermée pour inventaire. Les démarches restaient possibles en ligne.',
            'niveau' => 'information',
        ]);

        // make:feature:seeders
    }
}
