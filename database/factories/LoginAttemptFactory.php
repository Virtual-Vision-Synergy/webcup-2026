<?php

namespace Database\Factories;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginAttempt>
 */
class LoginAttemptFactory extends Factory
{
    /** @var array<int, string> */
    private const USER_AGENTS = [
        'Mozilla/5.0 (Linux; Android 14; SM-A146B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1',
        'python-requests/2.32.3',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'user_id' => null,
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->randomElement(self::USER_AGENTS),
            'successful' => false,
            'reason' => LoginAttempt::REASON_BAD_CREDENTIALS,
            'created_at' => fake()->dateTimeBetween('-24 hours'),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['email' => $user->email, 'user_id' => $user->id]);
    }

    public function lockedOut(): static
    {
        return $this->state(fn (): array => ['reason' => LoginAttempt::REASON_LOCKED_OUT]);
    }

    public function successful(): static
    {
        return $this->state(fn (): array => ['successful' => true, 'reason' => null]);
    }
}
