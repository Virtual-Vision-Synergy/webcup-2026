<?php

namespace Database\Factories;

use App\Models\AlerteCanicule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlerteCanicule>
 */
class AlerteCaniculeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $niveau = fake()->randomElement(AlerteCanicule::NIVEAU_OPTIONS);
        $debut = now()->subDays(fake()->numberBetween(0, 2));

        return [
            'user_id' => User::factory(),
            'secteur' => fake()->randomElement(['Quartier du Port', 'Plateau Nord', 'Vieille Ville', 'Zone Industrielle', 'Collines de l\'Est', 'Rive Sud']),
            'niveau' => $niveau,
            'temperature_max' => match ($niveau) {
                'vigilance' => fake()->numberBetween(35, 37),
                'alerte' => fake()->numberBetween(38, 40),
                default => fake()->numberBetween(41, 45),
            },
            'debut' => $debut->toDateString(),
            'fin' => $debut->addDays(fake()->numberBetween(2, 6))->toDateString(),
            'message' => 'Une vague de chaleur extrême touche le secteur. Limitez vos efforts aux heures les plus chaudes.',
        ];
    }
}
