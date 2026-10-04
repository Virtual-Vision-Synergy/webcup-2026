<?php

namespace Database\Factories;

use App\Models\Remontee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Remontee>
 */
class RemonteeFactory extends Factory
{
    /** @var list<array{0: string, 1: string, 2: string}> */
    private const EXEMPLES = [
        ['comprendre', 'À quoi sert mon numéro de téléphone ?', 'J’ai donné mon numéro à l’inscription. Je ne sais pas qui peut le voir ni pourquoi la mairie en a besoin.'],
        ['acceder', 'Voir tout ce que la mairie sait sur moi', 'Je voudrais savoir quelles informations sont enregistrées sur moi dans la plateforme.'],
        ['corriger', 'Mon quartier est faux', 'J’ai déménagé à Ambohimanarina, mais je reçois encore les alertes de mon ancien quartier.'],
        ['supprimer', 'Que devient mon historique si je pars ?', 'Si je supprime mon compte, mes anciennes démarches sont-elles vraiment effacées ?'],
        ['autre', 'Les agents voient-ils mes messages ?', 'Je me demande si tous les agents de la mairie peuvent lire mes messages au service des eaux.'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$categorie, $objet, $message] = fake()->randomElement(self::EXEMPLES);

        return [
            'user_id' => User::factory(),
            'categorie' => $categorie,
            'objet' => $objet,
            'message' => $message,
            'statut' => 'recue',
            'envoyee_le' => now()->subDays(fake()->numberBetween(1, 10)),
        ];
    }

    /**
     * Numéro de suivi au même format que Remontee::envoyer().
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Remontee $remontee): void {
            if ($remontee->reference === null) {
                $remontee->reference = sprintf('%s-%s-%06d', Remontee::PREFIXE_REFERENCE, ($remontee->envoyee_le ?? now())->format('Y'), $remontee->id);
                $remontee->saveQuietly();
            }
        });
    }

    public function priseEnCompte(?User $agent = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'statut' => 'prise_en_compte',
            'prise_en_compte_le' => now()->subDay(),
            'pris_en_charge_par' => $agent ?? User::factory()->agent(),
        ]);
    }

    public function repondue(?User $agent = null): static
    {
        return $this->priseEnCompte($agent)->state(fn (array $attributes): array => [
            'statut' => 'repondue',
            'reponse' => 'Bonjour, votre numéro de téléphone ne sert qu’à vous joindre au sujet de vos démarches. Seuls les agents qui traitent vos demandes peuvent le voir. Vous pouvez le retirer à tout moment dans votre profil.',
            'repondue_le' => now()->subHours(3),
            'repondue_par' => fn (array $attributes) => $attributes['pris_en_charge_par'],
        ]);
    }
}
