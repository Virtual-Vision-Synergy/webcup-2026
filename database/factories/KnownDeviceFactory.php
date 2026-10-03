<?php

namespace Database\Factories;

use App\Models\KnownDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KnownDevice>
 */
class KnownDeviceFactory extends Factory
{
    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private const APPAREILS = [
        ['Chrome', 'Windows', KnownDevice::TYPE_ORDINATEUR],
        ['Firefox', 'Linux', KnownDevice::TYPE_ORDINATEUR],
        ['Safari', 'iOS', KnownDevice::TYPE_MOBILE],
        ['Chrome', 'Android', KnownDevice::TYPE_MOBILE],
        ['Safari', 'iPadOS', KnownDevice::TYPE_TABLETTE],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$browser, $os, $type] = fake()->randomElement(self::APPAREILS);
        $vuLe = fake()->dateTimeBetween('-20 days', '-1 hour');

        return [
            'user_id' => User::factory(),
            'device_token_hash' => hash('sha256', Str::random(40)),
            'browser' => $browser,
            'os' => $os,
            'device_type' => $type,
            'ip_approx' => fake()->numberBetween(41, 197).'.'.fake()->numberBetween(0, 255).'.'.fake()->numberBetween(0, 255).'.x',
            'first_seen_at' => fake()->dateTimeBetween('-60 days', $vuLe),
            'last_seen_at' => $vuLe,
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()->subDay()]);
    }
}
