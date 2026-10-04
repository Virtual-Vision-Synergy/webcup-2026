<?php

namespace Database\Factories;

use App\Models\Signalement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Signalement>
 */
class SignalementFactory extends Factory
{
    /** Problèmes crédibles pour la démo, par catégorie. */
    private const DESCRIPTIONS = [
        'eclairage' => [
            'Le lampadaire devant le numéro 12 est cassé, la rue est plongée dans le noir depuis trois jours.',
            'Deux lampadaires clignotent en permanence le long du trottoir, près de l\'arrêt de bus.',
        ],
        'voirie' => [
            'Un nid-de-poule profond s\'est formé au milieu de la chaussée, les deux-roues font des écarts dangereux.',
            'Une plaque d\'égout est descellée et bouge au passage des voitures.',
        ],
        'proprete' => [
            'Dépôt sauvage de sacs poubelles et d\'encombrants au coin de la rue depuis le week-end.',
            'La poubelle publique déborde et les déchets s\'envolent sur le trottoir.',
        ],
        'eau' => [
            'Fuite d\'eau sur la canalisation du trottoir, l\'eau coule en continu vers la chaussée.',
            'Caniveau bouché : la rue est inondée à chaque averse.',
        ],
        'espaces_verts' => [
            'Une grosse branche est tombée dans le square et bloque l\'allée principale.',
        ],
        'mobilier' => [
            'Le banc de l\'abribus est arraché et présente des vis saillantes.',
            'Le panneau « Stop » du carrefour est couché au sol.',
        ],
        'autre' => [
            'Un tag injurieux a été peint sur le mur de l\'école primaire.',
        ],
    ];

    /** Lieux de démo. */
    private const LIEUX = [
        'Rue des Lumières, devant le n° 12',
        'Avenue de l\'Indépendance, près de la pharmacie',
        'Carrefour d\'Analakely',
        'Square d\'Isoraka, entrée nord',
        'Rue Andrianary Ratianarivo, arrêt de bus',
        'Boulevard de l\'Europe, à hauteur du marché',
        'Place de l\'Hôtel de Ville',
        'Rue du Port, face à l\'école primaire',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categorie = fake()->randomElement(array_keys(self::DESCRIPTIONS));

        return [
            'user_id' => User::factory(),
            'categorie' => $categorie,
            'description' => fake()->randomElement(self::DESCRIPTIONS[$categorie]),
            'lieu' => fake()->randomElement(self::LIEUX),
            'photo' => null,
            'statut' => fake()->randomElement(Signalement::STATUT_OPTIONS),
        ];
    }

    /**
     * Signalement tout juste déposé (état « Nouveau »).
     */
    public function nouveau(): static
    {
        return $this->state(fn (array $attributes) => ['statut' => 'nouveau']);
    }
}
