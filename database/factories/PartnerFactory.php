<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake('fr_FR')->company(),
            'type' => fake()->randomElement(Partner::TYPE_OPTIONS),
            'description' => fake('fr_FR')->sentence(12),
            'address' => fake('fr_FR')->streetAddress().', Nova Terra',
            'phone' => '+261 20 00 '.fake()->numerify('### ##'),
            'email' => fake()->safeEmail(),
            'website' => 'https://'.fake()->domainWord().'.example.org',
            'latitude' => fake()->randomFloat(7, -18.93, -18.89),
            'longitude' => fake()->randomFloat(7, 47.50, 47.54),
            'opening_hours' => self::semaine(
                [['start' => '08:30', 'end' => '12:00'], ['start' => '14:00', 'end' => '17:30']],
                ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'],
            ),
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    /**
     * Mêmes plages pour les jours indiqués, fermé les autres jours.
     *
     * @param  array<int, array{start: string, end: string}>  $plages
     * @param  array<int, string>  $jours
     * @return array<string, array<int, array{start: string, end: string}>>
     */
    public static function semaine(array $plages, array $jours): array
    {
        $semaine = [];

        foreach (Partner::JOURS as $jour) {
            $semaine[$jour] = in_array($jour, $jours, true) ? $plages : [];
        }

        return $semaine;
    }
}
