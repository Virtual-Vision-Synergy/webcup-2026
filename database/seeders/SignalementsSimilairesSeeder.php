<?php

namespace Database\Seeders;

use App\Models\Signalement;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * F75 : plusieurs habitants signalent le même problème, pour la démo du regroupement côté agent.
 */
class SignalementsSimilairesSeeder extends Seeder
{
    /** Groupes de doublons : [catégorie, [[lieu, description], …]]. */
    private const GROUPES = [
        ['eau', [
            ['Rue Rainandriamampandry, près du marché couvert', 'Grosse fuite d\'eau sur la canalisation, l\'eau coule sur la chaussée depuis ce matin.'],
            ['Rue Rainandriamampandry, devant le marché couvert', 'Fuite d\'eau importante, la canalisation est percée et la chaussée est inondée.'],
            ['Marché couvert, rue Rainandriamampandry', 'L\'eau jaillit de la canalisation cassée, toute la rue est inondée.'],
            ['Rue Rainandriamampandry', 'Canalisation percée près du marché : fuite d\'eau continue.'],
        ]],
        ['eclairage', [
            ['Avenue Ravoninahitriniarivo, arrêt de bus Ankorondrano', 'Les lampadaires de l\'arrêt de bus sont éteints, c\'est dangereux le soir.'],
            ['Arrêt de bus Ankorondrano, avenue Ravoninahitriniarivo', 'Plus aucun lampadaire allumé à l\'arrêt de bus, on ne voit rien le soir.'],
            ['Ankorondrano, arrêt de bus', 'Lampadaires éteints autour de l\'arrêt de bus depuis une semaine.'],
        ]],
        ['voirie', [
            ['Route digue, sortie du pont d\'Anosizato', 'Énorme nid-de-poule sur la route digue juste après le pont, les motos tombent.'],
            ['Pont d\'Anosizato, route digue', 'Nid-de-poule très profond à la sortie du pont, accident évité de justesse.'],
        ]],
    ];

    public function run(): void
    {
        $citoyens = User::query()->get()->filter(fn (User $user): bool => $user->isCitoyen())->values();

        if ($citoyens->isEmpty()) {
            return;
        }

        foreach (self::GROUPES as [$categorie, $signalements]) {
            foreach ($signalements as $index => [$lieu, $description]) {
                Signalement::factory()->nouveau()->for($citoyens[$index % $citoyens->count()])->create([
                    'categorie' => $categorie,
                    'lieu' => $lieu,
                    'description' => $description,
                    'created_at' => now()->subHours(count($signalements) - $index),
                ]);
            }
        }
    }
}
