<?php

namespace Database\Factories;

use App\Models\Demarche;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Demarche>
 */
class DemarcheFactory extends Factory
{
    /** Objets de démarches crédibles pour la démo. */
    private const TITRES = [
        "Demande d'acte de naissance",
        'Demande de copie d\'acte de mariage',
        'Certificat de résidence',
        'Déclaration de naissance',
        'Demande de permis de construire',
        'Inscription scolaire en école primaire',
        'Raccordement au réseau d\'eau potable',
        'Autorisation d\'occupation du domaine public',
        'Légalisation de signature',
        'Signalement d\'un éclairage public défaillant',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service_id' => Service::factory(),
            'titre' => fake()->randomElement(self::TITRES),
            'description' => fake('fr_FR')->paragraphs(2, true),
            'statut' => fake()->randomElement(Demarche::STATUT_OPTIONS),
        ];
    }

    /**
     * Demande déposée au cours des 7 derniers jours (le graphique du tableau de bord n'est pas vide).
     */
    public function recente(): static
    {
        return $this->state(function (array $attributes): array {
            $date = fake()->dateTimeBetween('-6 days', 'now');

            return ['created_at' => $date, 'updated_at' => $date];
        });
    }
}
