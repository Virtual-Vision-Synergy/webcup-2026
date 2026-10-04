<?php

namespace Database\Factories;

use App\Models\Idea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Idea>
 */
class IdeaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->citoyen(),
            'title' => fake()->randomElement([
                'Des bancs ombragés près du marché',
                'Un composteur collectif par quartier',
                'Une navette le dimanche',
                'Un atelier de réparation de vélos',
                'Plus de bornes d’eau potable',
                'Des ateliers numériques pour les aînés',
            ]),
            'description' => fake('fr_FR')->paragraphs(2, true),
            'category' => fake()->randomElement(Idea::CATEGORY_OPTIONS),
            'status' => 'recue',
        ];
    }

    /**
     * Numéro de suivi au format IDE-2026-000012, dérivé de l'identifiant comme en production.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Idea $idea): void {
            if ($idea->reference === null) {
                $idea->reference = Idea::referencePour($idea);
                $idea->saveQuietly();
            }
        });
    }

    public function aLEtude(): static
    {
        return $this->state(fn () => ['status' => 'a_l_etude', 'status_changed_at' => now()]);
    }

    public function retenue(): static
    {
        return $this->state(fn () => [
            'status' => 'retenue',
            'status_changed_at' => now(),
            'response' => 'Bonne idée : elle sera mise en place dans les prochains mois.',
            'responded_at' => now(),
        ]);
    }

    public function nonRetenue(): static
    {
        return $this->state(fn () => [
            'status' => 'non_retenue',
            'status_changed_at' => now(),
            'response' => 'Cette idée ne peut pas être retenue pour le moment.',
            'responded_at' => now(),
        ]);
    }

    public function masquee(): static
    {
        return $this->state(fn () => ['hidden_at' => now()]);
    }
}
