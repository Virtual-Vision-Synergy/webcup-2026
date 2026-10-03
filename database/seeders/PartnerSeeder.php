<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Partenaires de Nova Terra (F74). Idempotent : mis à jour par slug, jamais dupliqués.
 * Numéros et e-mails fictifs (+261 20 00 …, @exemple.mg).
 */
class PartnerSeeder extends Seeder
{
    public function run(): void
    {
        $auteur = User::query()->where('role_id', Role::idFor(Role::AGENT))->value('id')
            ?? User::query()->where('role_id', Role::idFor(Role::ADMIN))->value('id');

        foreach ($this->partenaires() as $slug => $data) {
            $partner = Partner::query()->firstOrNew(['slug' => $slug]);
            $partner->fill($data);
            // Champs réservés (hors #[Fillable]) : assignés ici, jamais par une saisie.
            $partner->forceFill(['slug' => $slug, 'is_published' => true]);
            $partner->created_by ??= $auteur;
            $partner->save();
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function partenaires(): array
    {
        $pause = [['08:30', '12:00'], ['14:00', '17:30']];

        return [
            'centre-de-sante-d-ampefiloha' => [
                'name' => 'Centre de santé d\'Ampefiloha',
                'type' => 'sante',
                'description' => 'Consultations de médecine générale, vaccinations et suivi des femmes enceintes, sans rendez-vous le matin.',
                'address' => '12 rue des Manguiers, Ampefiloha, Nova Terra',
                'phone' => '+261 20 00 000 01',
                'email' => 'accueil@centre-sante.exemple.mg',
                'website' => null,
                'latitude' => -18.9121,
                'longitude' => 47.5187,
                // Pause déjeuner en semaine, samedi matin seulement.
                'opening_hours' => [1 => $pause, 2 => $pause, 3 => $pause, 4 => $pause, 5 => $pause, 6 => [['08:30', '12:00']]],
            ],
            'banque-alimentaire-fanampiana' => [
                'name' => 'Banque alimentaire Fanampiana',
                'type' => 'social',
                'description' => 'Colis alimentaires et vêtements pour les familles en difficulté, sur présentation d\'une attestation du CCAS.',
                'address' => '4 allée de l\'Entraide, Isotry, Nova Terra',
                'phone' => '+261 20 00 000 02',
                'email' => 'contact@fanampiana.exemple.mg',
                'website' => null,
                'latitude' => -18.9065,
                'longitude' => 47.5089,
                // Fermé le week-end.
                'opening_hours' => [1 => [['09:00', '16:00']], 2 => [['09:00', '16:00']], 3 => [['09:00', '12:00']], 4 => [['09:00', '16:00']], 5 => [['09:00', '16:00']]],
            ],
            'transports-urbains-de-nova-terra' => [
                'name' => 'Transports urbains de Nova Terra — agence Analakely',
                'type' => 'transport',
                'description' => 'Abonnements, cartes de bus, objets trouvés et informations sur les lignes et perturbations.',
                'address' => 'Gare routière d\'Analakely, avenue de l\'Indépendance, Nova Terra',
                'phone' => '+261 20 00 000 03',
                'email' => 'agence@transports-nt.exemple.mg',
                'website' => 'https://transports-nt.exemple.mg',
                'latitude' => -18.9058,
                'longitude' => 47.5251,
                'opening_hours' => [1 => [['07:00', '19:00']], 2 => [['07:00', '19:00']], 3 => [['07:00', '19:00']], 4 => [['07:00', '19:00']], 5 => [['07:00', '19:00']], 6 => [['08:00', '13:00']]],
            ],
            'maison-de-l-emploi-et-de-la-formation' => [
                'name' => 'Maison de l\'emploi et de la formation',
                'type' => 'emploi',
                'description' => 'Offres d\'emploi, aide au CV, ateliers et inscriptions aux formations professionnelles de la ville.',
                'address' => '28 boulevard du Travail, Behoririka, Nova Terra',
                'phone' => '+261 20 00 000 04',
                'email' => 'emploi@maison-emploi.exemple.mg',
                'website' => 'https://maison-emploi.exemple.mg',
                'latitude' => -18.9002,
                'longitude' => 47.5216,
                'opening_hours' => [1 => $pause, 2 => $pause, 3 => $pause, 4 => $pause, 5 => [['08:30', '12:00']]],
            ],
        ];
    }
}
