<?php

namespace Database\Factories;

use App\Models\Signalement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Signalement>
 */
class SignalementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'titre' => rtrim(fake('fr_FR')->sentence(3), '.'),
            'description' => fake('fr_FR')->paragraphs(2, true),
            'niveau' => fake()->randomElement(Signalement::NIVEAU_OPTIONS),
            'zone' => fake()->randomElement(['Analakely', 'Isoraka', 'Ankorondrano', 'Ivandry', 'Ambohijatovo', 'Behoririka', 'Andohalo']),
            'photo' => null,
            'date_incident' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'latitude' => fake()->randomFloat(7, -18.95, -18.85),
            'longitude' => fake()->randomFloat(7, 47.48, 47.56),
        ];
    }
}
