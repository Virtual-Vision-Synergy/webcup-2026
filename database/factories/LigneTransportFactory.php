<?php

namespace Database\Factories;

use App\Models\LigneTransport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LigneTransport>
 */
class LigneTransportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quartiers = ['Gare centrale', 'Hôtel de ville', 'Marché couvert', 'Université', 'Hôpital', 'Stade municipal', 'Port', 'Zone industrielle', 'Lycée Jean-Moulin', 'Parc des Lacs', 'Cité des Fleurs', 'Aéroport'];
        $arrets = fake()->randomElements($quartiers, fake()->numberBetween(5, 8));

        return [
            'user_id' => User::factory(),
            'numero' => (string) fake()->numberBetween(1, 40),
            'nom' => $arrets[0].' – '.end($arrets),
            'mode' => fake()->randomElement(LigneTransport::MODE_OPTIONS),
            'arrets' => implode("\n", $arrets),
            'horaires' => "Lundi au vendredi : 5 h 30 – 21 h 00\nSamedi : 6 h 00 – 20 h 00\nDimanche et jours fériés : 7 h 00 – 19 h 00",
            'frequence' => 'Toutes les '.fake()->randomElement([8, 10, 12, 15, 20]).' min',
            'etat' => 'normal',
            'perturbation' => null,
        ];
    }

    public function perturbee(): static
    {
        return $this->state(fn () => [
            'etat' => 'perturbe',
            'perturbation' => 'Travaux de voirie : arrêts déviés, retards de 10 à 15 minutes.',
        ]);
    }
}
