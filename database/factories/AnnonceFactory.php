<?php

namespace Database\Factories;

use App\Models\Annonce;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Annonce>
 */
class AnnonceFactory extends Factory
{
    /**
     * Par défaut : un message en cours de diffusion.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->agent(),
            'titre' => fake()->randomElement([
                'Coupure d’eau programmée',
                'Alerte météo : vents forts',
                'Fermeture exceptionnelle de la mairie annexe',
                'Collecte des déchets décalée',
                'Travaux sur la ligne de navette',
            ]),
            'contenu' => fake('fr_FR')->sentence(18),
            'niveau' => fake()->randomElement(Annonce::NIVEAU_OPTIONS),
            'debut' => now()->subHour(),
            'fin' => now()->addDay(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['debut' => now()->subHour(), 'fin' => now()->addDay()]);
    }

    public function programmee(): static
    {
        return $this->state(fn () => ['debut' => now()->addDay(), 'fin' => now()->addDays(2)]);
    }

    public function expiree(): static
    {
        return $this->state(fn () => ['debut' => now()->subDays(3), 'fin' => now()->subDays(2)]);
    }
}
