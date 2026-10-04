<?php

namespace Database\Factories;

use App\Models\AbonnementLigne;
use App\Models\LigneTransport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbonnementLigne>
 */
class AbonnementLigneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ligne_transport_id' => LigneTransport::factory(),
            'arret' => null,
        ];
    }
}
