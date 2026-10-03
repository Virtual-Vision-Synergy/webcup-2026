<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sujets = [
            'Question sur les permis de construire',
            'Signalement d\'un problème dans la rue',
            'Demande de renseignement sur les services',
            'Plainte concernant les nettoyage public',
            'Suggestion pour améliorer la mairie',
        ];

        return [
            'user_id' => User::factory(),
            'nom' => fake('fr_FR')->name(),
            'email' => fake('fr_FR')->safeEmail(),
            'sujet' => fake()->randomElement($sujets),
            'message' => fake('fr_FR')->paragraphs(2, true),
        ];
    }
}
