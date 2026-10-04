<?php

namespace Database\Seeders;

use App\Models\LoginAttempt;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * F85 / F100 : événements de sécurité de démo sur les derniers jours, pour que le fil « Sécurité »
 * de l'espace agent et son widget du tableau de bord ne soient pas vides.
 * Complète LoginAttemptSeeder (F37) et TentativeBloqueeSeeder (F81) : un compte désactivé, un nouvel appareil,
 * des accès refusés répétés, une rafale, des modifications massives et un verrouillage.
 */
class SecurityEventSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'user@example.com')->first();
        $voisin = User::where('email', 'voisin@example.com')->first() ?? User::query()->citizens()->inRandomOrder()->first();
        $autre = User::query()->citizens()->whereNotIn('id', array_filter([$user?->id, $voisin?->id]))->inRandomOrder()->first();

        $evenements = [
            [SecurityEvent::TYPE_NOUVEL_APPAREIL, $user, SecurityEvent::NIVEAU_INFO, '102.16.44.12', 'Mozilla/5.0 (Linux; Android 14) Mobile', 26 * 60],
            [SecurityEvent::TYPE_CONNEXION_BLOQUEE, $user, SecurityEvent::NIVEAU_MOYEN, '102.16.44.12', 'Mozilla/5.0 (Windows NT 10.0)', 48],
            [SecurityEvent::TYPE_ACCES_REFUSES, $voisin, SecurityEvent::NIVEAU_MOYEN, '41.207.51.18', 'Mozilla/5.0 (Windows NT 10.0)', 3 * 60],
            [SecurityEvent::TYPE_RAFALE, $autre, SecurityEvent::NIVEAU_MOYEN, '196.192.40.7', 'python-requests/2.32.3', 9 * 60],
            [SecurityEvent::TYPE_MODIFICATIONS_MASSIVES, $autre, SecurityEvent::NIVEAU_ELEVE, '196.192.40.7', 'python-requests/2.32.3', 9 * 60 - 5],
            [SecurityEvent::TYPE_COMPTE_VERROUILLE, $autre, SecurityEvent::NIVEAU_INFO, null, null, 9 * 60 - 10],
            [SecurityEvent::TYPE_NOUVEL_APPAREIL, $voisin, SecurityEvent::NIVEAU_INFO, '154.126.9.33', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5)', 3 * 24 * 60],
        ];

        foreach ($evenements as [$type, $compte, $niveau, $ip, $userAgent, $ilYaMinutes]) {
            $event = new SecurityEvent;
            $event->forceFill([
                'type' => $type,
                'user_id' => $compte?->id,
                'niveau' => $niveau,
                'description' => SecurityEvent::TYPE_OPTIONS[$type].' (données de démonstration).',
                'details' => [],
                'ip' => $ip,
                'user_agent' => $userAgent,
                'created_at' => now()->subMinutes($ilYaMinutes),
            ])->save();
        }

        // Tentatives sur le compte désactivé de démo (F37), affichées comme « Compte désactivé » dans le fil.
        $desactive = User::where('email', 'desactive@example.com')->first();

        if ($desactive !== null) {
            LoginAttempt::factory(2)->forUser($desactive)->create([
                'reason' => LoginAttempt::REASON_DEACTIVATED,
                'ip' => '41.74.212.90',
                'created_at' => now()->subHours(2),
            ]);
        }
    }
}
