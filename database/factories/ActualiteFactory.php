<?php

namespace Database\Factories;

use App\Models\Actualite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Actualite>
 */
class ActualiteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $actualites = [
            ['titre' => 'Travaux de rénovation rue principale', 'contenu' => 'La rue principale sera en travaux du 10 au 25 octobre. La circulation sera limitée. Merci de votre compréhension.'],
            ['titre' => 'Nouvelle bibliothèque municipale ouverte', 'contenu' => 'La bibliothèque rénové accueille maintenant des ordinateurs, des espaces de travail et une salle jeunesse.'],
            ['titre' => 'Élections municipales : les inscriptions sont ouvertes', 'contenu' => 'Si vous souhaitez voter, inscrivez-vous avant le 30 octobre à la mairie.'],
            ['titre' => 'Festival culturel du 15 novembre', 'contenu' => 'Retrouvez-nous pour un jour de célébrations : musique, danse, gastronomie locale et spectacles pour enfants.'],
            ['titre' => 'Collecte des déchets modifiée en octobre', 'contenu' => 'À cause des travaux, la collecte sera avancée d\'un jour pour les quartiers nord.'],
        ];

        $actualite = fake()->randomElement($actualites);

        return [
            'user_id' => User::factory(),
            'titre' => $actualite['titre'],
            'contenu' => $actualite['contenu'],
            'date' => fake()->dateTimeBetween('-2 weeks', '+2 weeks')->format('Y-m-d'),
            'image' => null,
        ];
    }
}
