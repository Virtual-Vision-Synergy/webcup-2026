<?php

namespace Database\Factories;

use App\Models\Quartier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Quartier>
 */
class QuartierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nom = 'Quartier '.fake()->unique()->lastName();

        return [
            'nom' => $nom,
            'slug' => Str::slug($nom),
        ];
    }
}
