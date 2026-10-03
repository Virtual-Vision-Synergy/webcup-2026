<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nom' => 'Service '.fake('fr_FR')->unique()->lastName(),
            'description' => fake('fr_FR')->paragraphs(2, true),
            'horaires' => "Lundi au vendredi : 8 h 00 – 12 h 00 et 13 h 30 – 17 h 00\nSamedi : 8 h 30 – 12 h 00",
            'telephone' => '+261 20 22 '.fake()->numerify('### ##'),
            'email' => fake()->unique()->userName().'@mairie-novaterra.mg',
            'adresse' => fake()->numberBetween(1, 120).' avenue de la République, Nova Terra',
        ];
    }
}
