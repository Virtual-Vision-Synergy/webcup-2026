<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    /** Lundi au vendredi avec pause déjeuner, fermé le week-end. */
    public const HORAIRES_SEMAINE = [
        1 => [['08:30', '12:00'], ['14:00', '17:30']],
        2 => [['08:30', '12:00'], ['14:00', '17:30']],
        3 => [['08:30', '12:00'], ['14:00', '17:30']],
        4 => [['08:30', '12:00'], ['14:00', '17:30']],
        5 => [['08:30', '12:00'], ['14:00', '17:30']],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory()->agent(),
            'name' => 'Association '.fake('fr_FR')->lastName(),
            'type' => fake()->randomElement(Partner::TYPE_OPTIONS),
            'description' => fake('fr_FR')->sentence(12),
            'address' => fake('fr_FR')->streetAddress().', Nova Terra',
            'phone' => '+261 20 00 '.fake()->numerify('### ##'),
            'email' => fake()->boolean() ? 'contact@'.fake()->domainWord().'.exemple.mg' : null,
            'website' => null,
            'latitude' => fake()->randomFloat(7, -18.95, -18.85),
            'longitude' => fake()->randomFloat(7, 47.48, 47.56),
            'opening_hours' => self::HORAIRES_SEMAINE,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }
}
