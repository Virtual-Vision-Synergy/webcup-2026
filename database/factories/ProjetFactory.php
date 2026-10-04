<?php

namespace Database\Factories;

use App\Models\Projet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Projet>
 */
class ProjetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $etapes = ['Étude et concertation', 'Appel d\'offres', 'Travaux', 'Mise en service'];
        $terminees = fake()->numberBetween(0, count($etapes));

        return [
            'user_id' => User::factory(),
            'titre' => 'Rénovation '.fake()->randomElement(['de la rue des Palmiers', 'du marché couvert', 'de la place centrale', 'du stade municipal']),
            'categorie' => fake()->randomElement(Projet::CATEGORIE_OPTIONS),
            'resume' => 'Des travaux pour rendre le quartier plus sûr et plus agréable.',
            'description' => fake()->paragraph(),
            'etat' => fake()->randomElement(Projet::ETAT_OPTIONS),
            'etapes' => implode("\n", $etapes),
            'etapes_terminees' => $terminees,
            'avancement' => (int) round($terminees / count($etapes) * 100),
            'date_debut' => fake()->dateTimeBetween('-1 year', 'now'),
            'date_fin' => fake()->dateTimeBetween('+1 month', '+2 years'),
            'budget' => fake()->boolean(70) ? fake()->numberBetween(5, 500) * 1_000_000 : null,
            'lieu' => null,
            'latitude' => null,
            'longitude' => null,
        ];
    }
}
