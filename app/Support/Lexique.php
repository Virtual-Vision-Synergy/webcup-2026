<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Lexique de la mairie (D13) : source unique des mots administratifs expliqués simplement.
 * Utilisé par la page publique /lexique et par l'infobulle <x-tn.terme slug="...">.
 * Les textes français sont les clés de traduction (lang/en.json les traduit).
 */
class Lexique
{
    /**
     * Termes en français (langue de référence), indexés par slug.
     *
     * @var array<string, array{terme: string, definition: string, exemple: string|null, voir_aussi: list<string>}>
     */
    public const TERMES = [
        'alerte' => [
            'terme' => 'Alerte et vigilance',
            'definition' => 'Un message urgent de la mairie sur un danger dans la ville ou dans votre quartier. « Vigilance » veut dire : restez attentif ; « Alerte » veut dire : suivez tout de suite les consignes.',
            'exemple' => 'Vigilance orages : évitez les bords de rivière ce soir.',
            'voir_aussi' => ['quartier', 'notification'],
        ],
        'ccas' => [
            'terme' => 'CCAS',
            'definition' => 'Le centre communal d’action sociale. C’est le service de la mairie qui aide les personnes en difficulté : aides financières, logement, personnes âgées ou handicapées.',
            'exemple' => 'Vous avez du mal à payer une facture d’eau : prenez rendez-vous avec le CCAS.',
            'voir_aussi' => ['rendez-vous', 'demarche'],
        ],
        'code-de-secours' => [
            'terme' => 'Code de secours',
            'definition' => 'Un code de remplacement, à utiliser une seule fois, si vous n’avez plus votre téléphone pour la vérification en deux étapes. Gardez ces codes dans un endroit sûr.',
            'exemple' => null,
            'voir_aussi' => ['verification-deux-etapes'],
        ],
        'demarche' => [
            'terme' => 'Démarche',
            'definition' => 'Une demande que vous faites à la mairie pour obtenir un papier ou un service. Vous la déposez en ligne, puis vous suivez son avancement.',
            'exemple' => 'Demander un acte de naissance est une démarche.',
            'voir_aussi' => ['statut', 'justificatif-de-domicile', 'piece-d-identite'],
        ],
        'etat-civil' => [
            'terme' => 'État civil',
            'definition' => 'Le service qui enregistre les grands moments de la vie : naissance, mariage, décès. Il délivre les actes qui le prouvent.',
            'exemple' => 'Un acte de mariage se demande au service de l’état civil.',
            'voir_aussi' => ['demarche'],
        ],
        'justificatif-de-domicile' => [
            'terme' => 'Justificatif de domicile',
            'definition' => 'Un document récent qui prouve votre adresse, à votre nom. Il a en général moins de trois mois.',
            'exemple' => 'Une facture d’électricité, d’eau ou une quittance de loyer.',
            'voir_aussi' => ['piece-d-identite', 'demarche'],
        ],
        'notification' => [
            'terme' => 'Notification',
            'definition' => 'Un petit message qui vous prévient d’une nouveauté : réponse de la mairie, demande qui avance, alerte. Elles sont rangées sous l’icône en forme de cloche.',
            'exemple' => null,
            'voir_aussi' => ['alerte', 'statut'],
        ],
        'piece-d-identite' => [
            'terme' => 'Pièce d’identité',
            'definition' => 'Un document officiel avec votre photo qui prouve qui vous êtes. Il doit être en cours de validité.',
            'exemple' => 'Une carte d’identité, un passeport ou un titre de séjour.',
            'voir_aussi' => ['justificatif-de-domicile'],
        ],
        'quartier' => [
            'terme' => 'Quartier',
            'definition' => 'La partie de la ville où vous habitez. En l’indiquant dans votre profil, vous recevez les alertes qui vous concernent.',
            'exemple' => null,
            'voir_aussi' => ['alerte'],
        ],
        'rendez-vous' => [
            'terme' => 'Rendez-vous et horaire',
            'definition' => 'Un moment réservé avec un agent de la mairie. Vous choisissez un horaire disponible, puis vous venez au guichet avec les documents demandés.',
            'exemple' => 'Mardi à 10 h 30, au service de l’état civil.',
            'voir_aussi' => ['etat-civil', 'piece-d-identite'],
        ],
        'signalement' => [
            'terme' => 'Signalement',
            'definition' => 'Un message pour prévenir la mairie d’un problème dans la rue ou dans un lieu public. La mairie le transmet au bon service.',
            'exemple' => 'Un lampadaire cassé, un nid-de-poule, des déchets abandonnés.',
            'voir_aussi' => ['statut', 'quartier'],
        ],
        'statut' => [
            'terme' => 'Statut d’une demande',
            'definition' => 'L’étape où en est votre demande. Déposée : la mairie l’a reçue. En cours : un agent s’en occupe. Traitée : c’est terminé. Refusée : la demande n’a pas pu être acceptée, la raison vous est expliquée.',
            'exemple' => null,
            'voir_aussi' => ['demarche', 'signalement'],
        ],
        'urbanisme' => [
            'terme' => 'Urbanisme',
            'definition' => 'Le service qui vérifie les travaux et les constructions dans la ville. Il faut lui demander l’autorisation avant de construire ou de modifier une maison.',
            'exemple' => 'Construire un garage, poser une clôture, agrandir une maison.',
            'voir_aussi' => ['demarche', 'rendez-vous'],
        ],
        'verification-deux-etapes' => [
            'terme' => 'Vérification en deux étapes',
            'definition' => 'Une protection en plus de votre mot de passe. À chaque connexion, vous tapez aussi un code à 6 chiffres affiché par une application sur votre téléphone.',
            'exemple' => null,
            'voir_aussi' => ['code-de-secours'],
        ],
    ];

    /**
     * Termes traduits dans la langue courante, triés par ordre alphabétique.
     *
     * @return array<string, array{slug: string, terme: string, definition: string, exemple: string|null, voir_aussi: list<string>, lettre: string}>
     */
    public static function termes(): array
    {
        $termes = [];

        foreach (self::TERMES as $slug => $entree) {
            $terme = __($entree['terme']);

            $termes[$slug] = [
                'slug' => $slug,
                'terme' => $terme,
                'definition' => __($entree['definition']),
                'exemple' => $entree['exemple'] !== null ? __($entree['exemple']) : null,
                'voir_aussi' => array_values(array_filter($entree['voir_aussi'], fn (string $lien): bool => array_key_exists($lien, self::TERMES))),
                'lettre' => Str::upper(Str::substr(Str::ascii($terme), 0, 1)),
            ];
        }

        uasort($termes, fn (array $a, array $b): int => strcmp(Str::lower(Str::ascii($a['terme'])), Str::lower(Str::ascii($b['terme']))));

        return $termes;
    }

    /**
     * Un terme traduit, ou null si le slug est inconnu.
     *
     * @return array{slug: string, terme: string, definition: string, exemple: string|null, voir_aussi: list<string>, lettre: string}|null
     */
    public static function terme(string $slug): ?array
    {
        return self::termes()[$slug] ?? null;
    }

    /**
     * Termes dont le mot ou la définition contient la recherche (sans tenir compte des accents ni des majuscules).
     *
     * @return array<string, array{slug: string, terme: string, definition: string, exemple: string|null, voir_aussi: list<string>, lettre: string}>
     */
    public static function rechercher(string $recherche): array
    {
        $recherche = self::normaliser($recherche);

        if ($recherche === '') {
            return self::termes();
        }

        return array_filter(
            self::termes(),
            fn (array $terme): bool => str_contains(self::normaliser($terme['terme'].' '.$terme['definition']), $recherche),
        );
    }

    /**
     * Regroupe des termes par première lettre (A–Z).
     *
     * @param  array<string, array{slug: string, terme: string, definition: string, exemple: string|null, voir_aussi: list<string>, lettre: string}>  $termes
     * @return array<string, list<array{slug: string, terme: string, definition: string, exemple: string|null, voir_aussi: list<string>, lettre: string}>>
     */
    public static function parLettre(array $termes): array
    {
        $groupes = [];

        foreach ($termes as $terme) {
            $groupes[$terme['lettre']][] = $terme;
        }

        ksort($groupes);

        return $groupes;
    }

    private static function normaliser(string $texte): string
    {
        return Str::lower(trim(Str::ascii($texte)));
    }
}
