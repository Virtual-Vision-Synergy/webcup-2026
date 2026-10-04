<?php

namespace Database\Seeders;

use App\Models\TentativeBloquee;
use Illuminate\Database\Seeder;

/**
 * F81 : envois bloqués de démo sur les dernières 24 h, pour que « Robots bloqués » soit parlant dans /admin :
 * une vague de robots sur l'inscription (champ piège), des envois directs sans formulaire, quelques rafales.
 */
class TentativeBloqueeSeeder extends Seeder
{
    public function run(): void
    {
        // Vague de robots sur l'inscription depuis une même source : champ piège rempli.
        TentativeBloquee::factory(12)
            ->sequence(fn ($s) => ['created_at' => now()->subHours(5)->addMinutes($s->index)])
            ->create([
                'formulaire' => TentativeBloquee::FORMULAIRE_INSCRIPTION,
                'motif' => TentativeBloquee::MOTIF_HONEYPOT,
                'ip' => '185.220.101.47',
                'user_agent' => 'python-requests/2.32.3',
            ]);

        // Envois directs sur la connexion sans passer par le formulaire.
        TentativeBloquee::factory(6)->create([
            'formulaire' => TentativeBloquee::FORMULAIRE_CONNEXION,
            'motif' => TentativeBloquee::MOTIF_JETON_INVALIDE,
            'user_agent' => 'curl/8.5.0',
        ]);

        // Rafale d'inscriptions depuis une IP : limite de débit atteinte.
        TentativeBloquee::factory(4)->create([
            'formulaire' => TentativeBloquee::FORMULAIRE_INSCRIPTION,
            'motif' => TentativeBloquee::MOTIF_DEBIT,
            'ip' => '41.188.37.204',
        ]);

        // Quelques envois trop rapides, réalistes sur les formulaires de contact et de signalement.
        TentativeBloquee::factory(3)->create([
            'formulaire' => TentativeBloquee::FORMULAIRE_CONTACT,
            'motif' => TentativeBloquee::MOTIF_TROP_RAPIDE,
        ]);
        TentativeBloquee::factory(2)->create([
            'formulaire' => TentativeBloquee::FORMULAIRE_SIGNALEMENT,
            'motif' => TentativeBloquee::MOTIF_HONEYPOT,
        ]);
    }
}
