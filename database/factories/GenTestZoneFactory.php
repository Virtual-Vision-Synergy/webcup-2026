<?php

namespace Database\Factories;

use App\Models\GenTestZone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenTestZone>
 */
class GenTestZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nom' => fake('fr_FR')->name(),
        ];
    }
}
