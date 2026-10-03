<?php

namespace Database\Factories;

use App\Models\CreneauRendezVous;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Rendez-vous confirmé sur un créneau qui lui est réservé (le créneau pointe vers lui après création).
 *
 * @extends Factory<RendezVous>
 */
class RendezVousFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'creneau_id' => CreneauRendezVous::factory(),
            'service_id' => fn (array $attributes) => CreneauRendezVous::query()->whereKey($attributes['creneau_id'])->value('service_id'),
            'motif' => fake()->optional()->randomElement([
                'Demande de copie intégrale d’acte de naissance.',
                'Renseignements pour un dossier de mariage.',
                'Dépôt d’un permis de construire pour une extension.',
                'Demande d’aide pour les frais de scolarité.',
                'Changement d’adresse après un déménagement.',
            ]),
            'statut' => 'confirme',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (RendezVous $rendezVous): void {
            if ($rendezVous->statut !== 'annule') {
                CreneauRendezVous::query()->whereKey($rendezVous->creneau_id)->update(['rendez_vous_id' => $rendezVous->id]);
            }
        });
    }

    public function annule(): static
    {
        return $this->state(fn (): array => ['statut' => 'annule', 'annule_le' => now()]);
    }
}
