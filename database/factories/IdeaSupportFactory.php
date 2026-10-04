<?php

namespace Database\Factories;

use App\Models\Idea;
use App\Models\IdeaSupport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdeaSupport>
 */
class IdeaSupportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->citoyen(),
            'idea_id' => Idea::factory(),
        ];
    }
}
