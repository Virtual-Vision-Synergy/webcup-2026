<?php

namespace Database\Factories;

use App\Models\Projet;
use App\Models\Quartier;
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
        $debut = now()->addDays(fake()->numberBetween(-180, 90));

        return [
            'user_id' => User::factory()->agent(),
            'quartier_id' => Quartier::query()->inRandomOrder()->value('id'),
            'titre' => fake()->randomElement([
                'Réfection de la rue des Pionniers',
                'Nouvel éclairage du marché central',
                'Agrandissement de la médiathèque',
                'Pistes cyclables du boulevard Est',
            ]),
            'description' => 'La ville améliore ce lieu du quotidien pour le rendre plus sûr et plus agréable. Les habitants seront prévenus avant chaque phase de travaux.',
            'etat' => fake()->randomElement(Projet::ETAT_OPTIONS),
            'date_debut' => $debut->toDateString(),
            'date_fin' => $debut->copy()->addMonths(fake()->numberBetween(2, 18))->toDateString(),
            'budget' => fake()->boolean(70) ? fake()->numberBetween(50, 2000) * 1_000_000 : null,
            'etapes' => "Concertation avec les habitants\nÉtudes techniques\nTravaux\nMise en service",
            'etapes_terminees' => fake()->numberBetween(0, 4),
            'latitude' => fake()->randomFloat(7, -18.93, -18.89),
            'longitude' => fake()->randomFloat(7, 47.50, 47.54),
        ];
    }
}
