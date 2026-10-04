<?php

namespace Database\Factories;

use App\Models\InterruptionTransport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterruptionTransport>
 */
class InterruptionTransportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'cause' => 'Pont fermé pour travaux urgents : les bus ne peuvent plus passer.',
            'arrets_touches' => null,
            'debut' => now()->subHour(),
            'fin' => now()->addHours(6),
            'solutions' => [
                [
                    'type' => 'navette',
                    'titre' => 'Navette gratuite de remplacement',
                    'description' => 'Montez devant l’arrêt habituel, panneau jaune « Navette ».',
                    'horaires' => 'Toutes les 15 min, de 6 h à 20 h',
                    'ligne_id' => null,
                    'latitude' => -18.9137,
                    'longitude' => 47.5361,
                ],
            ],
            'notified_at' => null,
        ];
    }

    public function terminee(): static
    {
        return $this->state(fn (): array => ['debut' => now()->subDays(2), 'fin' => now()->subDay()]);
    }

    public function programmee(): static
    {
        return $this->state(fn (): array => ['debut' => now()->addDay(), 'fin' => now()->addDays(2)]);
    }
}
