<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class PromouvoirAdmin extends Command
{
    protected $signature = 'app:promouvoir-admin {email : E-mail d\'un utilisateur déjà inscrit}';

    protected $description = 'Donne le rôle admin à un utilisateur existant (accès à /admin), sans tinker';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Aucun utilisateur avec l'adresse {$email}. Il doit d'abord s'inscrire.");

            return self::FAILURE;
        }

        if ($user->isAdmin()) {
            $this->info("{$email} est déjà administrateur.");

            return self::SUCCESS;
        }

        $user->changerRole(Role::where('code', Role::ADMIN)->firstOrFail());

        $this->info("{$email} est maintenant administrateur.");

        return self::SUCCESS;
    }
}
