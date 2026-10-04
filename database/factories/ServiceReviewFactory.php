<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceReview>
 */
class ServiceReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service_id' => Service::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->randomElement([
                'Accueil aimable et dossier traité rapidement, merci.',
                'Beaucoup d’attente au guichet, mais l’agent a pris le temps de bien expliquer.',
                'Les horaires affichés ne correspondaient pas : porte fermée à mon arrivée.',
                'Démarche simple, j’ai reçu la réponse en quelques jours.',
                'Personnel compétent, locaux un peu petits pour le nombre de personnes.',
            ]),
            'verified_usage' => false,
        ];
    }

    public function masque(string $motif = 'injurieux'): static
    {
        return $this->state(fn (): array => [
            'hidden_at' => now(),
            'hidden_reason' => $motif,
        ]);
    }
}
