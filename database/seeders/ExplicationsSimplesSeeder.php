<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * F90 : explications simples de départ pour les passages administratifs des pages, et synonymes « mot difficile → mot simple ».
 * Idempotent : une clé ou un mot déjà présent n'est pas modifié (l'admin a pu le corriger).
 * Appelé par DatabaseSeeder (démo locale) et par la migration 2026_10_04_140002 (production, sans db:seed).
 */
class ExplicationsSimplesSeeder extends Seeder
{
    /** @var array<string, array{titre: string, texte_officiel: string, explication: string, termes: list<string>}> */
    public const EXPLICATIONS = [
        'depot-demarche' => [
            'titre' => 'Déposer une démarche',
            'texte_officiel' => 'Toute demande adressée à l’administration communale doit être accompagnée des pièces justificatives requises. Le délai d’instruction court à compter de la réception d’un dossier complet ; l’usager est informé de l’avancement par voie électronique.',
            'explication' => 'Quand vous demandez quelque chose à la mairie, ajoutez les documents qui prouvent ce que vous dites (pièce d’identité, justificatif de domicile…). La mairie commence à traiter votre demande quand elle a tout reçu. Vous êtes prévenu par message à chaque étape.',
            'termes' => ['demarche', 'piece-d-identite', 'justificatif-de-domicile'],
        ],
        'statut-demarche' => [
            'titre' => 'Suivi d’une démarche',
            'texte_officiel' => 'Le statut de la demande est mis à jour par le service instructeur. Une décision de rejet est motivée. L’accusé de réception atteste du dépôt de la demande et en mentionne la date.',
            'explication' => 'Le statut montre où en est votre demande. C’est l’agent qui s’en occupe qui le change. Si la demande est refusée, la raison vous est expliquée. L’accusé de réception est un papier qui prouve que vous avez bien déposé votre demande, et quel jour.',
            'termes' => ['statut', 'demarche'],
        ],
        'rendez-vous-conditions' => [
            'titre' => 'Conditions des rendez-vous',
            'texte_officiel' => 'L’usager se présente au guichet à l’horaire convenu, muni des pièces justificatives requises. Tout rendez-vous non honoré sans annulation préalable pourra être réattribué.',
            'explication' => 'Venez à l’heure choisie avec les documents demandés. Si vous ne pouvez pas venir, annulez votre rendez-vous avant : sinon, l’horaire peut être donné à quelqu’un d’autre.',
            'termes' => ['rendez-vous', 'piece-d-identite'],
        ],
        'signalement-traitement' => [
            'titre' => 'Traitement des signalements',
            'texte_officiel' => 'Les signalements sont transmis au service compétent qui procède à leur qualification. La commune ne saurait être tenue à un délai d’intervention ; les situations présentant un danger imminent relèvent des services d’urgence.',
            'explication' => 'Votre message est envoyé au bon service de la mairie, qui vérifie le problème. La mairie ne peut pas promettre une date de réparation. S’il y a un danger tout de suite (blessé, incendie…), appelez les secours d’urgence.',
            'termes' => ['signalement', 'statut'],
        ],
        'verification-deux-etapes' => [
            'titre' => 'Vérification en deux étapes',
            'texte_officiel' => 'L’activation de l’authentification à deux facteurs renforce la sécurité du compte. Les codes de récupération doivent être conservés en lieu sûr ; ils permettent l’accès au compte en cas de perte du dispositif d’authentification.',
            'explication' => 'En plus du mot de passe, vous tapez un code affiché sur votre téléphone. C’est plus sûr. Gardez les codes de secours dans un endroit sûr : ils servent si vous perdez votre téléphone.',
            'termes' => ['verification-deux-etapes', 'code-de-secours'],
        ],
    ];

    /** @var array<string, string> */
    public const SYNONYMES = [
        'accusé de réception' => 'papier qui prouve que la mairie a reçu votre demande',
        'administration communale' => 'la mairie',
        'à compter de' => 'à partir de',
        'authentification' => 'vérification de votre identité à la connexion',
        'commune' => 'la ville et sa mairie',
        'délai d’instruction' => 'temps que met la mairie pour étudier votre demande',
        'dispositif' => 'appareil (votre téléphone)',
        'honoré' => 'respecté (vous êtes venu)',
        'imminent' => 'qui va arriver tout de suite',
        'motivée' => 'expliquée par une raison',
        'pièces justificatives' => 'documents qui prouvent ce que vous dites',
        'préalable' => 'faite avant',
        'qualification' => 'vérification du problème',
        'réattribué' => 'donné à quelqu’un d’autre',
        'rejet' => 'refus',
        'service compétent' => 'le service de la mairie qui s’en occupe',
        'service instructeur' => 'le service qui étudie votre demande',
        'usager' => 'vous (l’habitant)',
        'voie électronique' => 'message sur le site ou par e-mail',
    ];

    public function run(): void
    {
        $maintenant = now();

        $clesExistantes = DB::table('explications_simples')->pluck('cle')->all();
        $explications = [];

        foreach (self::EXPLICATIONS as $cle => $entree) {
            if (! in_array($cle, $clesExistantes, true)) {
                $explications[] = [
                    'cle' => $cle,
                    'titre' => $entree['titre'],
                    'texte_officiel' => $entree['texte_officiel'],
                    'explication' => $entree['explication'],
                    'termes' => json_encode($entree['termes']),
                    'actif' => true,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ];
            }
        }

        if ($explications !== []) {
            DB::table('explications_simples')->insert($explications);
        }

        $motsExistants = DB::table('synonymes_simples')->pluck('mot')->all();
        $synonymes = [];

        foreach (self::SYNONYMES as $mot => $equivalent) {
            if (! in_array($mot, $motsExistants, true)) {
                $synonymes[] = ['mot' => $mot, 'equivalent' => $equivalent, 'created_at' => $maintenant, 'updated_at' => $maintenant];
            }
        }

        if ($synonymes !== []) {
            DB::table('synonymes_simples')->insert($synonymes);
        }
    }
}
