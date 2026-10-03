<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $services = [
            ['nom' => 'Demandes de documents', 'description' => 'Demandez vos certificats (naissances, mariages, décès) en ligne.', 'icone' => 'document-text'],
            ['nom' => 'Paiements en ligne', 'description' => 'Payez vos taxes, permis et amendes de manière sécurisée.', 'icone' => 'credit-card'],
            ['nom' => 'Signalement de problèmes', 'description' => 'Signalez les nids-de-poule, défauts d\'éclairage ou autres soucis publics.', 'icone' => 'exclamation-triangle'],
            ['nom' => 'Réservation d\'équipements', 'description' => 'Réservez les salles communales, terrains de sport et espaces publics.', 'icone' => 'calendar'],
            ['nom' => 'Permis de construire', 'description' => 'Téléchargez les formulaires et suivez l\'état de votre demande.', 'icone' => 'home-modern'],
        ];

        $service = fake()->randomElement($services);

        return [
            'user_id' => User::factory(),
            'nom' => $service['nom'],
            'description' => $service['description'],
            'icone' => $service['icone'],
        ];
    }
}
