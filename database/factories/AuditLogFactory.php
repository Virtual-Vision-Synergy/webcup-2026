<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Entrées de journal pour les tests et la démo (en production, seul AuditLogger écrit).
 *
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nom = fake()->randomElement(['État civil', 'Urbanisme', 'Voirie', 'Eau et assainissement']);

        return [
            'actor_id' => null,
            'actor_name' => 'Système',
            'actor_role' => null,
            'action' => 'updated',
            'subject_type' => 'Service',
            'subject_id' => fake()->numberBetween(1, 50),
            'subject_label' => 'Service : '.$nom,
            'changes' => ['horaires' => ['avant' => 'Lun-Ven 8h-16h', 'apres' => 'Lun-Sam 8h-12h']],
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => fake()->dateTimeBetween('-5 days'),
        ];
    }

    /**
     * Auteur : instantané du nom et du rôle de l'utilisateur.
     */
    public function par(User $user): static
    {
        return $this->state(fn (): array => [
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'actor_role' => $user->role->label,
        ]);
    }

    public function action(string $action): static
    {
        return $this->state(fn (): array => ['action' => $action]);
    }
}
