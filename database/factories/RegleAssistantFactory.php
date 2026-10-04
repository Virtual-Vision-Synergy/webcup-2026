<?php

namespace Database\Factories;

use App\Models\RegleAssistant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegleAssistant>
 */
class RegleAssistantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'declencheurs' => 'rendez-vous, rdv',
            'reponse' => 'Vous pouvez réserver un créneau au guichet d’un service, en ligne.',
            'lien_libelle' => 'Prendre rendez-vous',
            'lien_url' => '/rendez-vous/prendre',
            'actif' => true,
        ];
    }
}
