<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceInterruption;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceInterruption>
 */
class ServiceInterruptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'type' => 'maintenance',
            'motif' => fake()->randomElement([
                'Mise à jour du logiciel de gestion',
                'Travaux de rénovation de l’accueil',
                'Coupure d’électricité dans le bâtiment',
            ]),
            'alternative' => 'Accueil possible à la mairie annexe, 12 rue du Port, du lundi au vendredi de 8 h à 12 h.',
            'alternative_service_id' => null,
            'debut_at' => now()->subHours(2),
            'retour_prevu_at' => now()->addDays(2),
            'retabli_at' => null,
            'created_by' => User::factory()->agent(),
        ];
    }

    public function incident(): static
    {
        return $this->state(fn (): array => ['type' => 'incident']);
    }

    public function sansDateDeRetour(): static
    {
        return $this->state(fn (): array => ['retour_prevu_at' => null]);
    }

    /**
     * Interruption terminée (historique).
     */
    public function retablie(): static
    {
        return $this->state(fn (): array => [
            'debut_at' => now()->subDays(10),
            'retour_prevu_at' => now()->subDays(8),
            'retabli_at' => now()->subDays(8),
        ]);
    }
}
