<?php

namespace Database\Factories;

use App\Models\ConversationAssistant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationAssistant>
 */
class ConversationAssistantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation' => fake()->uuid(),
            'message' => fake()->randomElement(['ma poubel pas ramasée', 'je veu un papier de naissance', 'trou dans la route', 'bonjour']),
            'type' => fake()->randomElement(array_keys(ConversationAssistant::TYPE_LABELS)),
            'service_id' => null,
        ];
    }
}
