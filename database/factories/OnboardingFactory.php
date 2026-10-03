<?php

namespace Database\Factories;

use App\Models\Onboarding;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Onboarding>
 */
class OnboardingFactory extends Factory
{
    /**
     * Parcours jamais commencé.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service_id' => null,
            'service_visited_at' => null,
            'completed_at' => null,
            'skipped_at' => null,
        ];
    }

    public function passe(): static
    {
        return $this->state(fn () => ['skipped_at' => now()]);
    }

    public function termine(): static
    {
        return $this->state(fn () => ['service_visited_at' => now(), 'completed_at' => now()]);
    }
}
