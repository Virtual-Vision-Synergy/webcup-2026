<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * F91 : règles de départ de l'assistant d'orientation (questions fréquentes hors annuaire des services).
 * Idempotent (une règle déjà présente, retrouvée par ses déclencheurs, n'est pas dupliquée).
 * Appelé par DatabaseSeeder (démo locale) et par la migration 2026_10_04_100002 (production, sans db:seed).
 */
class ReglesAssistantSeeder extends Seeder
{
    /** @var list<array{declencheurs: string, reponse: string, lien_libelle: string, lien_url: string}> */
    public const REGLES = [
        [
            'declencheurs' => 'mot de passe, oublié, connexion, me connecter, compte bloqué, double authentification',
            'reponse' => 'Pour votre mot de passe ou la sécurité de votre compte, tout se règle dans vos paramètres.',
            'lien_libelle' => 'Sécurité de mon compte',
            'lien_url' => '/settings/security',
        ],
        [
            'declencheurs' => 'suivre ma demande, où en est, avancement, statut, réponse mairie, mes démarches',
            'reponse' => 'Vous pouvez suivre l’avancement de chacune de vos démarches et lire les réponses de la mairie.',
            'lien_libelle' => 'Voir mes démarches',
            'lien_url' => '/demarches',
        ],
        [
            'declencheurs' => 'rendez-vous, rdv, prendre rendez-vous, guichet',
            'reponse' => 'Vous pouvez réserver un créneau au guichet d’un service, en ligne.',
            'lien_libelle' => 'Prendre rendez-vous',
            'lien_url' => '/rendez-vous/prendre',
        ],
        [
            'declencheurs' => 'signaler, signalement, problème dans la rue, cassé',
            'reponse' => 'Pour prévenir la mairie d’un problème dans la rue ou dans un lieu public, faites un signalement : il sera transmis au bon service.',
            'lien_libelle' => 'Faire un signalement',
            'lien_url' => '/signalements/create',
        ],
        [
            'declencheurs' => 'horaires, heure d’ouverture, ouvert, fermé',
            'reponse' => 'Les horaires, l’adresse et le téléphone de chaque service sont indiqués sur sa fiche.',
            'lien_libelle' => 'Voir les services et leurs horaires',
            'lien_url' => '/services',
        ],
        [
            'declencheurs' => 'mot difficile, comprends pas, définition, signifie, signification, lexique',
            'reponse' => 'Le lexique explique simplement les mots administratifs (démarche, état civil, justificatif de domicile…).',
            'lien_libelle' => 'Ouvrir le lexique',
            'lien_url' => '/lexique',
        ],
        [
            'declencheurs' => 'contacter, parler à quelqu’un, agent, humain, téléphoner, écrire à la mairie',
            'reponse' => 'Vous pouvez écrire directement à la mairie : un agent vous répondra.',
            'lien_libelle' => 'Contacter la mairie',
            'lien_url' => '/messages/create',
        ],
        [
            'declencheurs' => 'urgence, urgent, pompiers, numéro d’urgence',
            'reponse' => 'En cas d’urgence, appelez tout de suite les secours. Les numéros utiles et les établissements de santé sont réunis ici.',
            'lien_libelle' => 'Numéros d’urgence',
            'lien_url' => '/urgences',
        ],
    ];

    public function run(): void
    {
        $maintenant = now();

        foreach (self::REGLES as $regle) {
            if (DB::table('regles_assistant')->where('declencheurs', $regle['declencheurs'])->exists()) {
                continue;
            }

            DB::table('regles_assistant')->insert([...$regle, 'actif' => true, 'created_at' => $maintenant, 'updated_at' => $maintenant]);
        }
    }
}
