<?php

namespace Database\Factories;

use App\Models\CreneauRendezVous;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreneauRendezVous>
 */
class CreneauRendezVousFactory extends Factory
{
    /**
     * Créneau de 30 minutes, à une demi-heure pile, entre 2 et 10 jours dans le futur.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $debut = now()->startOfHour()->addDays(fake()->numberBetween(2, 10))->addMinutes(30 * fake()->numberBetween(0, 1));

        return [
            'service_id' => Service::factory()->state(['duree_rendez_vous' => 30]),
            'debut' => $debut,
            'fin' => $debut->addMinutes(30),
        ];
    }

    /**
     * Créneau à une date précise (UTC), de la durée donnée.
     */
    public function a(string $debutUtc, int $minutes = 30): static
    {
        return $this->state(function () use ($debutUtc, $minutes): array {
            $debut = now()->parse($debutUtc, 'UTC');

            return ['debut' => $debut, 'fin' => $debut->addMinutes($minutes)];
        });
    }
}
