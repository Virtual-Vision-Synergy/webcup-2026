<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Database\Factories\PartnerFactory;
use Illuminate\Database\Seeder;

/**
 * Partenaires de Nova Terra (F74). Idempotent : mis à jour par slug, jamais dupliqué.
 * Coordonnées, numéros et e-mails fictifs.
 */
class PartnerSeeder extends Seeder
{
    public function run(): void
    {
        $auteur = User::query()->where('role_id', Role::idFor(Role::AGENT))->first()
            ?? User::query()->where('role_id', Role::idFor(Role::ADMIN))->first();

        $semaine = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'];

        $partenaires = [
            'centre-de-sante-fanilo' => [
                'name' => 'Centre de santé Fanilo',
                'type' => 'sante',
                'description' => 'Consultations de médecine générale, vaccinations et suivi des enfants, sans rendez-vous le matin.',
                'address' => '12 rue Ratsimilaho, quartier Analakely, Nova Terra',
                'phone' => '+261 20 00 000 01',
                'email' => 'accueil@fanilo.exemple.mg',
                'website' => null,
                'latitude' => -18.9065,
                'longitude' => 47.5225,
                // Pause déjeuner, fermé le week-end.
                'opening_hours' => PartnerFactory::semaine([['start' => '08:00', 'end' => '12:00'], ['start' => '14:00', 'end' => '17:30']], $semaine),
            ],
            'association-tsara-sakafo' => [
                'name' => 'Association Tsara Sakafo',
                'type' => 'social',
                'description' => 'Aide alimentaire : distribution de colis et repas chauds pour les familles en difficulté, sur simple inscription.',
                'address' => '3 allée des Jacarandas, quartier Isotry, Nova Terra',
                'phone' => '+261 20 00 000 02',
                'email' => 'contact@tsara-sakafo.exemple.mg',
                'website' => 'https://tsara-sakafo.example.org',
                'latitude' => -18.9150,
                'longitude' => 47.5120,
                'opening_hours' => PartnerFactory::semaine([['start' => '09:00', 'end' => '13:00']], ['mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi']),
            ],
            'nova-terra-transports' => [
                'name' => 'Nova Terra Transports',
                'type' => 'transport',
                'description' => 'Compagnie de transport urbain : abonnements, cartes de bus, objets trouvés et informations sur les lignes.',
                'address' => 'Gare routière centrale, avenue de l\'Indépendance, Nova Terra',
                'phone' => '+261 20 00 000 03',
                'email' => 'agence@transports.exemple.mg',
                'website' => 'https://transports-novaterra.example.org',
                'latitude' => -18.9100,
                'longitude' => 47.5260,
                'opening_hours' => PartnerFactory::semaine([['start' => '07:00', 'end' => '18:00']], [...$semaine, 'samedi']),
            ],
            'maison-de-l-emploi' => [
                'name' => 'Maison de l\'emploi et de la formation',
                'type' => 'emploi',
                'description' => 'Offres d\'emploi, aide au CV, ateliers de préparation aux entretiens et inscriptions aux formations.',
                'address' => '45 boulevard Ratsimandrava, quartier Ankorondrano, Nova Terra',
                'phone' => '+261 20 00 000 04',
                'email' => 'emploi@maison-emploi.exemple.mg',
                'website' => null,
                'latitude' => -18.8990,
                'longitude' => 47.5300,
                'opening_hours' => PartnerFactory::semaine([['start' => '08:30', 'end' => '12:00'], ['start' => '13:30', 'end' => '16:30']], $semaine),
            ],
        ];

        foreach ($partenaires as $slug => $data) {
            $partner = Partner::query()->firstOrNew(['slug' => $slug]);
            $partner->fill($data);
            $partner->slug = $slug;
            $partner->is_published = true;

            if ($partner->created_by === null && $auteur !== null) {
                $partner->creator()->associate($auteur);
            }

            $partner->save();
        }
    }
}
