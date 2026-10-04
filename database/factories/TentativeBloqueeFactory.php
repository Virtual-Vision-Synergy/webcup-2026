<?php

namespace Database\Factories;

use App\Models\TentativeBloquee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TentativeBloquee>
 */
class TentativeBloqueeFactory extends Factory
{
    /** @var array<int, string> */
    private const USER_AGENTS = [
        'python-requests/2.32.3',
        'curl/8.5.0',
        'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        'Go-http-client/1.1',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'formulaire' => fake()->randomElement(array_keys(TentativeBloquee::FORMULAIRE_OPTIONS)),
            'motif' => fake()->randomElement(array_keys(TentativeBloquee::MOTIF_OPTIONS)),
            'user_id' => null,
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->randomElement(self::USER_AGENTS),
            'created_at' => fake()->dateTimeBetween('-24 hours'),
        ];
    }
}
