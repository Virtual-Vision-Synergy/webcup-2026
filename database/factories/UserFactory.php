<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\Quartier;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'role_id' => fn () => Role::idFor(Role::CITOYEN),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Profil complet (étape 1 du parcours de prise en main).
     */
    public function profilComplet(): static
    {
        return $this->state(fn () => [
            'telephone' => fake()->numerify('034 ## ### ##'),
            'quartier_id' => fn () => Quartier::query()->inRandomOrder()->value('id'),
        ]);
    }

    /**
     * Habitant d'un quartier donné (slug : nord, sud, est, ouest, centre).
     */
    public function quartier(string $slug): static
    {
        return $this->state(fn () => ['quartier_id' => Quartier::idPour($slug)]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role_id' => Role::idFor(Role::ADMIN)]);
    }

    public function citoyen(): static
    {
        return $this->state(fn () => ['role_id' => Role::idFor(Role::CITOYEN)]);
    }

    public function agent(): static
    {
        return $this->state(fn () => ['role_id' => Role::idFor(Role::AGENT)]);
    }

    /**
     * F99 : compte partenaire rattaché au partenaire donné (ou à un nouveau partenaire).
     */
    public function partenaireDe(?Partner $partner = null): static
    {
        return $this->state(fn () => [
            'role_id' => Role::idFor(Role::PARTENAIRE),
            'partner_id' => $partner->id ?? Partner::factory(),
        ]);
    }

    /**
     * F70 : agent rattaché aux services donnés.
     */
    public function agentDe(Service ...$services): static
    {
        return $this->agent()->afterCreating(
            fn (User $user) => $user->services()->attach(array_map(fn (Service $service): int => $service->id, $services)),
        );
    }

    public function deactivated(): static
    {
        return $this->state(fn () => ['deactivated_at' => now()]);
    }
}
