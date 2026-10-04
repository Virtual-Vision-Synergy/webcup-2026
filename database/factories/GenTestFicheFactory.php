<?php

namespace Database\Factories;

use App\Models\GenTestFiche;
use App\Models\User;
use App\Models\GenTestZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenTestFiche>
 */
class GenTestFicheFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'gen_test_zone_id' => GenTestZone::factory(),
            'titre' => rtrim(fake('fr_FR')->sentence(3), '.'),
            'description' => fake('fr_FR')->paragraphs(2, true),
            'niveau' => fake()->randomElement(GenTestFiche::NIVEAU_OPTIONS),
            'statut' => fake()->randomElement(GenTestFiche::STATUT_OPTIONS),
        ];
    }
}
