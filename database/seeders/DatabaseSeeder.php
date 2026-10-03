<?php

namespace Database\Seeders;

use App\Models\Actualite;
use App\Models\Message;
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
     * En local : comptes admin@example.com / user@example.com (mot de passe : password).
     * En production : aucun compte avec un mot de passe connu n'est créé ;
     * les comptes jury sont créés à la main.
     */
    public function run(): void
    {
        if (! app()->isProduction()) {
            User::factory()->admin()->create([
                'name' => 'Admin Démo',
                'email' => 'admin@example.com',
            ]);

            User::factory()->create([
                'name' => 'Utilisateur Démo',
                'email' => 'user@example.com',
            ]);
        }

        User::factory(8)->create([
            'password' => Str::password(32),
        ]);

        $users = User::all();

        Service::factory(20)->recycle($users)->create();

        Actualite::factory(20)->recycle($users)->create();

        Message::factory(20)->recycle($users)->create();

        // make:feature:seeders
    }
}
