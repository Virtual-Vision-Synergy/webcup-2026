<?php

namespace Database\Factories;

use App\Models\ActionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActionLog>
 */
class ActionLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => fake()->randomElement(array_keys(ActionLog::ACTION_LABELS)),
            'subject_type' => 'User',
            'subject_id' => fake()->numberBetween(1, 50),
            'ip' => fake()->ipv4(),
            'created_at' => fake()->dateTimeBetween('-7 days'),
        ];
    }
}
