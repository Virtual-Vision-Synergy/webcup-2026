<?php

namespace Database\Seeders;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * F37 : tentatives de connexion de démo sur les dernières 24 h, pour que /agent/securite/connexions soit parlant :
 * erreurs isolées d'habitants, une IP qui essaie plusieurs comptes, un blocage sur user@example.com.
 */
class LoginAttemptSeeder extends Seeder
{
    public function run(): void
    {
        $citoyens = User::query()->citizens()->inRandomOrder()->limit(6)->get();

        // Habitants qui se trompent une ou deux fois puis réussissent.
        foreach ($citoyens->take(4) as $citoyen) {
            $ip = fake()->ipv4();
            $moment = now()->subMinutes(fake()->numberBetween(30, 1400));

            LoginAttempt::factory(fake()->numberBetween(1, 2))->forUser($citoyen)->create(['ip' => $ip, 'created_at' => $moment]);
            LoginAttempt::factory()->forUser($citoyen)->successful()->create(['ip' => $ip, 'created_at' => $moment->copy()->addMinute()]);
        }

        // Une même source qui essaie de nombreux comptes (existants et inventés) : signalée « Plusieurs comptes ».
        $ipSuspecte = '41.188.37.204';
        $cibles = $citoyens->pluck('email')->merge(['admin.mairie@example.com', 'contact@example.com', 'jean.rakoto@example.com']);

        foreach ($cibles as $index => $email) {
            $user = User::where('email', $email)->first();
            LoginAttempt::factory(3)->create([
                'email' => $email,
                'user_id' => $user?->id,
                'ip' => $ipSuspecte,
                'user_agent' => 'python-requests/2.32.3',
                'created_at' => now()->subHours(3)->addMinutes($index * 2),
            ]);
        }

        // Blocage du compte citoyen de démo : 5 échecs puis tentatives refusées.
        $demo = User::where('email', 'user@example.com')->first();

        if ($demo !== null) {
            $ip = '102.16.44.12';
            $debut = now()->subMinutes(50);

            LoginAttempt::factory(5)->forUser($demo)->sequence(fn ($s) => ['created_at' => $debut->copy()->addSeconds($s->index * 20)])
                ->create(['ip' => $ip]);
            LoginAttempt::factory(2)->forUser($demo)->lockedOut()->sequence(fn ($s) => ['created_at' => $debut->copy()->addMinutes(2 + $s->index)])
                ->create(['ip' => $ip]);
        }

        // Quelques échecs isolés sur des adresses inconnues.
        LoginAttempt::factory(6)->create();
    }
}
