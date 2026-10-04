<?php

namespace Database\Seeders;

use App\Models\AvailabilitySubscription;
use App\Models\Partner;
use App\Models\PartnerOffering;
use App\Models\Role;
use App\Models\User;
use Database\Factories\PartnerFactory;
use Illuminate\Database\Seeder;

/**
 * Services partenaires de démonstration (F99). Idempotent : mis à jour par slug, jamais dupliqué.
 * S'appuie sur les partenaires de PartnerSeeder (F74) ; un partenaire absent est ignoré.
 *
 * Hors production uniquement (mot de passe : password) :
 *   - partenaire.fanilo@example.com      Centre de santé Fanilo
 *   - partenaire.sakafo@example.com      Association Tsara Sakafo
 *   - partenaire.transports@example.com  Nova Terra Transports
 *   - user@example.com (citoyen) abonné à « Me prévenir » sur la distribution de colis (Complet).
 */
class PartnerOfferingSeeder extends Seeder
{
    /** @var array<string, string> e-mail du compte de démo → slug du partenaire */
    public const COMPTES = [
        'partenaire.fanilo@example.com' => 'centre-de-sante-fanilo',
        'partenaire.sakafo@example.com' => 'association-tsara-sakafo',
        'partenaire.transports@example.com' => 'nova-terra-transports',
    ];

    public function run(): void
    {
        $partenaires = Partner::query()->whereIn('slug', array_values(self::COMPTES))->get()->keyBy('slug');

        // Les événements de modèle (journal F47, notification des abonnés) n'ont pas lieu d'être pour des données de démo.
        PartnerOffering::withoutEvents(function () use ($partenaires): void {
            foreach ($this->services() as $slug => $data) {
                $partner = $partenaires->get($data['partner']);

                if ($partner === null) {
                    continue;
                }

                $offre = PartnerOffering::query()->firstOrNew(['slug' => $slug]);
                $offre->fill(collect($data)->except('partner')->all());
                $offre->slug = $slug;
                $offre->partner()->associate($partner);
                $offre->save();
            }
        });

        if (app()->isProduction()) {
            return;
        }

        foreach (self::COMPTES as $email => $slugPartenaire) {
            $partner = $partenaires->get($slugPartenaire);

            if ($partner === null) {
                continue;
            }

            $compte = User::query()->where('email', $email)->first()
                ?? User::factory()->createOne(['name' => 'Partenaire '.$partner->name, 'email' => $email]);

            $compte->forceFill(['role_id' => Role::idFor(Role::PARTENAIRE), 'partner_id' => $partner->id])->save();
        }

        $citoyen = User::query()->where('email', 'user@example.com')->first();
        $complet = PartnerOffering::query()->where('slug', 'distribution-de-colis-alimentaires')->first();

        if ($citoyen !== null && $complet !== null) {
            AvailabilitySubscription::abonner($citoyen, $complet);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function services(): array
    {
        $commun = ['booking_url' => null, 'contact_phone' => null, 'contact_email' => null, 'alternative_text' => null, 'alternative_url' => null, 'unavailable_until' => null, 'opening_hours' => null, 'is_published' => true];

        return [
            'consultation-de-medecine-generale' => [
                ...$commun,
                'partner' => 'centre-de-sante-fanilo',
                'title' => 'Consultation de médecine générale',
                'description' => "Consultation avec un médecin généraliste : fièvre, douleurs, renouvellement d'ordonnance, certificat médical.\nRéservez votre créneau en ligne pour éviter l'attente.",
                'conditions' => "Ouvert à tous les habitants. 5 000 Ar la consultation, gratuite pour les enfants de moins de 5 ans.\nApporter le carnet de santé et la liste des médicaments en cours.",
                'status' => PartnerOffering::STATUS_AVAILABLE,
                'booking_url' => 'https://rdv-fanilo.example.org/creneaux',
            ],
            'vaccination-des-enfants' => [
                ...$commun,
                'partner' => 'centre-de-sante-fanilo',
                'title' => 'Vaccination des enfants',
                'description' => 'Vaccins du calendrier national pour les enfants de 0 à 11 ans (BCG, polio, rougeole…).',
                'conditions' => 'Gratuit. Venir avec le carnet de vaccination de l\'enfant et un parent.',
                'status' => PartnerOffering::STATUS_FULL,
                'contact_phone' => '+261 20 00 000 11',
                'alternative_text' => 'Le centre de santé de base d\'Isotry vaccine aussi les enfants le mercredi matin, sans rendez-vous.',
                // Horaires propres : uniquement le mercredi matin et le vendredi matin.
                'opening_hours' => PartnerFactory::semaine([['start' => '08:00', 'end' => '11:30']], ['mercredi', 'vendredi']),
            ],
            'distribution-de-colis-alimentaires' => [
                ...$commun,
                'partner' => 'association-tsara-sakafo',
                'title' => 'Distribution de colis alimentaires',
                'description' => 'Colis de denrées de base (riz, huile, légumes secs) pour les familles en difficulté, une fois par semaine.',
                'conditions' => "Familles résidant à Nova Terra, sur simple inscription.\nApporter une pièce d'identité et un justificatif de domicile.",
                'status' => PartnerOffering::STATUS_FULL,
                'contact_email' => 'contact@tsara-sakafo.example.org',
                'alternative_text' => 'En attendant une place, les repas chauds solidaires de l\'association restent ouverts à tous.',
                'alternative_url' => 'https://tsara-sakafo.example.org/repas-chauds',
            ],
            'repas-chauds-solidaires' => [
                ...$commun,
                'partner' => 'association-tsara-sakafo',
                'title' => 'Repas chauds solidaires',
                'description' => 'Un repas chaud complet servi sur place, sans inscription, pour toute personne qui en a besoin.',
                'conditions' => 'Gratuit, sans condition. Accès en fauteuil roulant.',
                'status' => PartnerOffering::STATUS_AVAILABLE,
                'contact_phone' => '+261 20 00 000 21',
                'opening_hours' => PartnerFactory::semaine([['start' => '11:30', 'end' => '13:30']], ['mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi']),
            ],
            'abonnement-mensuel-de-bus' => [
                ...$commun,
                'partner' => 'nova-terra-transports',
                'title' => 'Abonnement mensuel de bus',
                'description' => 'Carte d\'abonnement valable sur toutes les lignes urbaines pendant un mois, rechargeable en ligne ou en agence.',
                'conditions' => "Plein tarif 30 000 Ar ; tarif réduit 15 000 Ar pour les étudiants et les plus de 65 ans (justificatif).\nUne photo d'identité pour la première carte.",
                'status' => PartnerOffering::STATUS_AVAILABLE,
                'booking_url' => 'https://transports-novaterra.example.org/abonnement',
            ],
            'objets-trouves' => [
                ...$commun,
                'partner' => 'nova-terra-transports',
                'title' => 'Objets trouvés',
                'description' => 'Récupération des objets oubliés dans les bus et à la gare routière.',
                'conditions' => 'Pièce d\'identité obligatoire pour récupérer un objet.',
                'status' => PartnerOffering::STATUS_UNAVAILABLE,
                'unavailable_until' => now(Partner::FUSEAU)->addDays(8)->toDateString(),
                'contact_phone' => '+261 20 00 000 31',
                'alternative_text' => 'Pendant le déménagement du guichet, déclarez votre perte en ligne : l\'agence vous rappelle si l\'objet est retrouvé.',
                'alternative_url' => 'https://transports-novaterra.example.org/objets-trouves',
            ],
            'navette-accessible-pmr' => [
                ...$commun,
                'partner' => 'nova-terra-transports',
                'title' => 'Navette accessible (personnes à mobilité réduite)',
                'description' => 'Transport à la demande pour les personnes en fauteuil roulant. En préparation : brouillon non publié.',
                'conditions' => 'Sur réservation 48 h à l\'avance.',
                'status' => PartnerOffering::STATUS_UNAVAILABLE,
                'is_published' => false,
            ],
        ];
    }
}
