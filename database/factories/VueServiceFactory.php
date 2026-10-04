<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\VueService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VueService>
 */
class VueServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'quartier_id' => null,
            'jour' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'nombre' => fake()->numberBetween(1, 40),
        ];
    }
}
