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
            ['nom' => 'Demandes de documents', 'description' => 'Demandez vos certificats (naissances, mariages, décès) en ligne.'],
            ['nom' => 'Paiements en ligne', 'description' => 'Payez vos taxes, permis et amendes de manière sécurisée.'],
            ['nom' => 'Signalement de problèmes', 'description' => 'Signalez les nids-de-poule, défauts d\'éclairage ou autres soucis publics.'],
            ['nom' => 'Réservation d\'équipements', 'description' => 'Réservez les salles communales, terrains de sport et espaces publics.'],
            ['nom' => 'Permis de construire', 'description' => 'Téléchargez les formulaires et suivez l\'état de votre demande.'],
        ];

        $service = fake()->randomElement($services);

        $nom = $service['nom'].' '.fake()->randomNumber(3);

        return [
            'user_id' => User::factory(),
            'nom' => $nom,
            'categorie' => fake()->randomElement(Service::CATEGORIE_OPTIONS),
            'description' => $service['description'],
            'horaires' => "Lundi au vendredi : 8 h 00 – 12 h 00 et 13 h 30 – 17 h 00\nSamedi : 8 h 30 – 12 h 00",
            'telephone' => '+261 20 22 '.fake()->numerify('### ##'),
            'email' => fake()->unique()->userName().'@mairie-novaterra.mg',
            'adresse' => fake()->numberBetween(1, 120).' avenue de la République, Nova Terra',
            'latitude' => fake()->randomFloat(7, -18.93, -18.89),
            'longitude' => fake()->randomFloat(7, 47.51, 47.54),
        ];
    }

    /**
     * Service mis en avant dans le catalogue et sur l'accueil.
     */
    public function misEnAvant(): static
    {
        return $this->state(fn (): array => ['mis_en_avant' => true]);
    }
}
