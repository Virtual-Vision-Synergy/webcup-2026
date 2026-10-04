<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * F27 : versions anglaises de quatre fiches de l'annuaire (nom, description, horaires, adresse, lieu du rendez-vous,
 * pièces à apporter). Idempotent : une traduction déjà présente (saisie par un agent) n'est jamais écrasée.
 * « Sports et associations » et « Élections et recensement » restent volontairement non traduits : leur fiche
 * montre le repli sur le français avec la mention dans la langue de l'habitant.
 * Aussi appelé par la migration de données 2026_10_04_130001 (la production ne lance jamais db:seed).
 */
class ServiceTranslationSeeder extends Seeder
{
    /**
     * Nom français du service => langue => champs traduits.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    public const TRADUCTIONS = [
        'État civil' => [
            'en' => [
                'nom' => 'Civil registry',
                'description' => "Birth, marriage and death certificates, family record books, signature certification and proof of residence.\nBooking an appointment is recommended for weddings and acknowledgements of paternity.",
                'horaires' => "Monday to Friday: 8:00 am – 4:00 pm\nSaturday: 8:00 am – 11:30 am (births and deaths desk)",
                'adresse' => 'Town hall, Independence Square, Nova Terra',
                'lieu_rendez_vous' => 'Town hall, ground floor, desk 2 (civil registry)',
                'pieces_a_fournir' => "Valid identity document\nFamily record book (if you have one)\nProof of address less than 3 months old",
            ],
        ],
        'Accueil de la Mairie' => [
            'en' => [
                'nom' => 'Town hall reception',
                'description' => 'First point of contact for residents: information on every procedure, guidance to the right service, administrative forms and appointment booking.',
                'horaires' => "Monday to Friday: 7:30 am – 5:00 pm\nSaturday: 8:00 am – 12:00 pm",
                'adresse' => 'Town hall, Independence Square, Nova Terra',
                'lieu_rendez_vous' => 'Town hall, reception hall, office 1',
                'pieces_a_fournir' => "Valid identity document\nAny letter or document related to your request",
            ],
        ],
        'Action sociale (CCAS)' => [
            'en' => [
                'nom' => 'Social welfare centre (CCAS)',
                'description' => 'The municipal social welfare centre supports families, older people and people with disabilities: emergency aid, meal delivery, postal address for people without a home, and referral to partner associations.',
                'horaires' => "Monday to Friday: 8:00 am – 12:00 pm and 1:30 pm – 4:30 pm\nWalk-in welcome on Wednesday mornings",
                'adresse' => '4 Solidarity Lane, Ambohitra district, Nova Terra',
                'lieu_rendez_vous' => 'Solidarity House, 8 Baobab Street, CCAS reception',
                'pieces_a_fournir' => "Valid identity document\nProof of address less than 3 months old\nProof of income for the last 3 months\nFamily record book (if you have one)",
            ],
        ],
        'Centre hospitalier de Nova Terra' => [
            'en' => [
                'nom' => 'Nova Terra Hospital',
                'description' => "The city's main hospital: adult emergency department open day and night, surgery, radiology and laboratory.\nIn a life-threatening emergency, call 124 first.",
                'horaires' => "Emergencies: 24/7\nConsultations: Monday to Friday, 8:00 am – 4:00 pm",
                'adresse' => 'Hospital Avenue, Ampefiloha district, Nova Terra',
            ],
        ],
    ];

    public function run(): void
    {
        self::remplir();
    }

    /**
     * Ajoute les traductions manquantes des services présents (recherchés par leur nom français).
     */
    public static function remplir(): void
    {
        $maintenant = now();

        foreach (self::TRADUCTIONS as $nom => $langues) {
            $serviceId = DB::table('services')->where('nom', $nom)->value('id');

            if ($serviceId === null) {
                continue;
            }

            foreach ($langues as $locale => $champs) {
                $existe = DB::table('service_translations')->where('service_id', $serviceId)->where('locale', $locale)->exists();

                if ($existe) {
                    continue;
                }

                DB::table('service_translations')->insert([
                    ...array_intersect_key($champs, array_flip(Service::TRADUCTIBLES)),
                    'service_id' => $serviceId,
                    'locale' => $locale,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ]);
            }
        }
    }
}
