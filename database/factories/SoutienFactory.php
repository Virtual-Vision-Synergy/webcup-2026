<?php

namespace Database\Factories;

use App\Models\Signalement;
use App\Models\Soutien;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Soutien>
 */
class SoutienFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->citoyen(),
            'signalement_id' => Signalement::factory()->nouveau(),
        ];
    }
}
