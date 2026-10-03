<?php

namespace Database\Factories;

use App\Models\Demarche;
use App\Models\DemarcheEtape;
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

    /** Messages d'agent crédibles pour la décision (D11). */
    private const COMMENTAIRES = [
        'traitee' => ['Votre document est prêt, à retirer à l\'accueil de la mairie avec une pièce d\'identité.', 'Dossier complet et validé.'],
        'refusee' => ['Il manque un justificatif de domicile de moins de 3 mois.', 'Cette demande relève de la préfecture et non de la mairie.'],
    ];

    /**
     * Étapes datées cohérentes avec le statut (suivi D11 réaliste dans la démo).
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Demarche $demarche): void {
            $statuts = match ($demarche->statut) {
                'en_cours' => ['en_cours'],
                'traitee', 'refusee' => ['en_cours', $demarche->statut],
                default => [],
            };

            $date = $demarche->created_at ?? now();

            foreach ($statuts as $statut) {
                $date = $date->copy()->addHours(fake()->numberBetween(2, 30));

                $etape = new DemarcheEtape;
                $etape->statut = $statut;
                $etape->commentaire = isset(self::COMMENTAIRES[$statut]) ? fake()->randomElement(self::COMMENTAIRES[$statut]) : null;
                $etape->created_at = $date->isFuture() ? now() : $date;
                $etape->updated_at = $etape->created_at;
                $demarche->etapes()->save($etape);
            }
        });
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
