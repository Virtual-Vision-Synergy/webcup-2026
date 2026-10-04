<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * F31 : recommandations canicule de l'Agence sanitaire de Nova Terra, par profil × niveau (un conseil par ligne).
 * Idempotent : un couple profil / niveau déjà présent (éventuellement modifié par l'admin) n'est pas écrasé.
 * Appelé par DatabaseSeeder (démo locale) et par la migration 2026_10_04_110004 (production, sans db:seed).
 */
class RecommandationsCaniculeSeeder extends Seeder
{
    /** @var array<string, array<string, list<string>>> */
    public const CONSEILS = [
        'tout_public' => [
            'vigilance' => [
                'Buvez de l’eau régulièrement, environ 1,5 litre par jour, même sans soif.',
                'Fermez volets et rideaux en journée, aérez la nuit quand il fait plus frais.',
                'Évitez les efforts physiques entre 11 h et 16 h.',
                'Prenez des nouvelles de vos voisins âgés ou isolés.',
            ],
            'alerte' => [
                'Buvez souvent de l’eau et mangez des fruits et légumes riches en eau.',
                'Restez au frais : passez au moins 2 à 3 heures par jour dans un lieu rafraîchi.',
                'Mouillez-vous le corps plusieurs fois par jour (douche, brumisateur, linge humide).',
                'Ne sortez pas aux heures les plus chaudes ; si vous sortez, chapeau et vêtements clairs.',
                'Ne laissez jamais une personne ou un animal dans une voiture garée.',
            ],
            'urgence' => [
                'Restez à l’intérieur, dans la pièce la plus fraîche, et limitez tout déplacement.',
                'Buvez de l’eau toutes les heures et rafraîchissez-vous très souvent.',
                'Appelez chaque jour les personnes fragiles de votre entourage.',
                'Maux de tête, vertiges, nausées, peau rouge et chaude : mettez la personne au frais, faites-la boire et appelez le 124.',
                'Perte de connaissance ou confusion : appelez immédiatement le 124 (SAMU).',
            ],
        ],
        'personnes_agees' => [
            'vigilance' => [
                'Buvez un verre d’eau toutes les heures, même sans soif : la sensation de soif diminue avec l’âge.',
                'Gardez la maison fraîche : volets fermés le jour, fenêtres ouvertes la nuit.',
                'Faites vos courses tôt le matin.',
                'Inscrivez-vous auprès du CCAS pour recevoir un appel en cas de forte chaleur.',
            ],
            'alerte' => [
                'Buvez régulièrement, environ 1,5 litre par jour, et mangez normalement.',
                'Restez dans la pièce la plus fraîche ; passez quelques heures dans un lieu climatisé (médiathèque, centre commercial).',
                'Rafraîchissez-vous le visage et les bras plusieurs fois par jour.',
                'Demandez à un proche ou un voisin de vous appeler ou de passer chaque jour.',
                'Ne prenez aucun médicament sans avis médical, même contre la fièvre.',
            ],
            'urgence' => [
                'Ne sortez pas de chez vous ; restez au frais, allongé si besoin.',
                'Buvez de l’eau très régulièrement et mouillez-vous la peau souvent.',
                'Gardez votre téléphone près de vous et faites-vous appeler matin et soir.',
                'Fatigue inhabituelle, confusion, chute ou fièvre : appelez le 124 sans attendre.',
            ],
        ],
        'enfants' => [
            'vigilance' => [
                'Proposez de l’eau très souvent à l’enfant, sans attendre qu’il la réclame.',
                'Habillez-le léger, en vêtements clairs, avec un chapeau dehors.',
                'Évitez les jeux en plein soleil entre 11 h et 16 h.',
            ],
            'alerte' => [
                'Donnez à boire très souvent ; pour un nourrisson, proposez le sein ou le biberon plus souvent.',
                'Gardez l’enfant à l’ombre et au frais ; ne le sortez pas aux heures chaudes.',
                'Rafraîchissez-le avec un linge humide ou un bain tiède (pas froid).',
                'Ne laissez jamais un enfant seul dans une voiture, même quelques minutes.',
                'Couchez le bébé dans une pièce aérée, en body seulement.',
            ],
            'urgence' => [
                'Gardez l’enfant à l’intérieur dans la pièce la plus fraîche toute la journée.',
                'Faites-le boire toutes les 30 minutes et surveillez ses couches ou ses passages aux toilettes.',
                'Somnolence, fièvre, vomissements, peau sèche et chaude : appelez le 124 immédiatement.',
                'Renseignez-vous sur la fermeture anticipée de l’école ou de la crèche.',
            ],
        ],
        'femmes_enceintes' => [
            'vigilance' => [
                'Buvez de l’eau régulièrement tout au long de la journée.',
                'Évitez les efforts et les sorties aux heures les plus chaudes.',
                'Portez des vêtements amples et légers.',
            ],
            'alerte' => [
                'Buvez souvent et fractionnez vos repas en petites portions.',
                'Reposez-vous au frais, les jambes surélevées.',
                'Évitez les transports bondés et l’attente au soleil.',
                'Gardez vos rendez-vous de suivi ; prévenez votre sage-femme si vous êtes très fatiguée.',
            ],
            'urgence' => [
                'Restez à l’intérieur au frais et limitez tout déplacement.',
                'Buvez de l’eau toutes les heures.',
                'Vertiges, maux de tête, contractions, saignements ou baisse des mouvements du bébé : appelez le 124 ou la maternité.',
            ],
        ],
        'malades_chroniques' => [
            'vigilance' => [
                'Gardez vos médicaments à l’abri de la chaleur (moins de 25 °C).',
                'Demandez à votre médecin si votre traitement doit être adapté pendant la chaleur.',
                'Buvez régulièrement, sauf si votre médecin limite vos boissons.',
            ],
            'alerte' => [
                'Suivez votre traitement sans l’arrêter et sans en ajouter sans avis médical.',
                'Restez au frais et évitez tout effort.',
                'Surveillez votre poids, votre tension ou votre glycémie plus souvent si vous en avez l’habitude.',
                'Gardez à portée de main la liste de vos médicaments et le numéro de votre médecin.',
            ],
            'urgence' => [
                'Restez à l’intérieur au frais toute la journée.',
                'Faites-vous appeler chaque jour par un proche.',
                'Essoufflement, douleur thoracique, malaise ou forte fièvre : appelez le 124 immédiatement.',
            ],
        ],
        'travailleurs_exterieur' => [
            'vigilance' => [
                'Commencez les tâches pénibles tôt le matin.',
                'Buvez un verre d’eau toutes les 20 minutes, même sans soif.',
                'Portez un chapeau et des vêtements légers et clairs.',
            ],
            'alerte' => [
                'Organisez le travail avant 11 h et après 16 h ; arrêtez les tâches pénibles aux heures chaudes.',
                'Faites une pause à l’ombre toutes les heures.',
                'Ne travaillez jamais seul : surveillez vos collègues et faites-vous surveiller.',
                'Crampes, nausées, vertiges : arrêtez-vous, mettez-vous à l’ombre et buvez.',
            ],
            'urgence' => [
                'Reportez les travaux en extérieur qui ne sont pas indispensables.',
                'Si le travail est indispensable : pauses de 15 minutes à l’ombre chaque heure et eau fraîche à volonté.',
                'Confusion, propos incohérents ou perte de connaissance d’un collègue : appelez le 124 et rafraîchissez-le.',
            ],
        ],
    ];

    public function run(): void
    {
        $maintenant = now();

        foreach (self::CONSEILS as $profil => $niveaux) {
            foreach ($niveaux as $niveau => $conseils) {
                if (DB::table('recommandations_canicule')->where('profil', $profil)->where('niveau', $niveau)->exists()) {
                    continue;
                }

                DB::table('recommandations_canicule')->insert([
                    'profil' => $profil,
                    'niveau' => $niveau,
                    'conseils' => implode("\n", $conseils),
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ]);
            }
        }
    }
}
