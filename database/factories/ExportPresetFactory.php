<?php

namespace Database\Factories;

use App\Models\ExportPreset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExportPreset>
 */
class ExportPresetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->agent(),
            'name' => fake()->randomElement(['Rapport hebdo transports', 'Suivi mensuel état civil', 'Demandes urgentes en cours']),
            'filters' => ['periode' => '7j', 'du' => '', 'au' => '', 'service' => '', 'statut' => '', 'priorite' => ''],
            'columns' => ['reference', 'created_at', 'service', 'statut', 'priorite'],
            'format' => 'csv',
        ];
    }
}
