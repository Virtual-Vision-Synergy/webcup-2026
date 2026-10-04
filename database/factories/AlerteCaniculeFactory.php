<?php

namespace Database\Factories;

use App\Models\AlerteCanicule;
use App\Models\Quartier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlerteCanicule>
 */
class AlerteCaniculeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'niveau' => 'alerte',
            'temperature_max' => fake()->numberBetween(36, 42),
            'debut' => now()->subHour(),
            'fin' => now()->addDays(2),
            'message' => 'Vague de chaleur extrême attendue : jusqu’à 40 °C l’après-midi. Protégez les personnes fragiles.',
            'notified_at' => null,
        ];
    }

    /**
     * Alerte visant les quartiers donnés (par slug : « nord », « sud »…).
     */
    public function pourQuartiers(string ...$slugs): static
    {
        return $this->afterCreating(function (AlerteCanicule $alerte) use ($slugs): void {
            $alerte->quartiers()->sync(Quartier::query()->whereIn('slug', $slugs)->pluck('id'));
            AlerteCanicule::oublierCache();
        });
    }

    public function programmee(): static
    {
        return $this->state(fn (): array => ['debut' => now()->addDay(), 'fin' => now()->addDays(3)]);
    }
}
