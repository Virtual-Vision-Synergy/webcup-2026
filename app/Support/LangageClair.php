<?php

namespace App\Support;

use App\Models\Annonce;
use App\Models\Service;
use App\Services\RedacteurLangageClair;

/**
 * F89 : « Version simple » en langage clair d'un service ou d'un message.
 *
 * - Version relue : le texte rédigé par un agent et validé (badge « Relu par la mairie »).
 * - Repli : aucune version validée → le texte officiel passé dans le RedacteurLangageClair (synonymes en base, sans IA),
 *   signalé comme « simplifié automatiquement ».
 * - Dans les deux cas, les éléments importants (délais, pièces, montants, contacts…) sont repris tels quels
 *   depuis les champs du service ou du message : la version simple ne les perd jamais.
 */
class LangageClair
{
    /** Délais écrits dans un texte : « sous 72 h », « dans 3 jours », « avant 15 jours »… */
    private const MOTIF_DELAI = '/(?:sous|dans|en|avant|pendant|environ|d[\'’]ici)[\s\x{00A0}\x{202F}]+\d+[\s\x{00A0}\x{202F}]*(?:heures?|h|jours?|semaines?|mois|minutes?|min)(?![\p{L}\p{N}])/iu';

    /** Montants : « 5 000 Ar », « 20 000 ariary », « 10 € »… */
    private const MOTIF_MONTANT = '/\d[\d\s\x{00A0}\x{202F}.,]*[\s\x{00A0}\x{202F}]?(?:Ar|ariary|MGA|€|euros?)(?![\p{L}])/iu';

    private const MOTIF_GRATUIT = '/(?<![\p{L}])gratuit(?:e|s|es|ement)?(?![\p{L}])/iu';

    /** Numéros de téléphone (+261…) et numéros d'urgence (117, 118, 124). */
    private const MOTIF_TELEPHONE = '/\+\d[\d\s\x{00A0}]{7,}\d|(?<![\d\p{L}])1(?:17|18|24)(?!\d)/u';

    /**
     * @return array{texte: string, relu: bool, essentiels: list<array{label: string, valeurs: list<string>}>}
     */
    public static function pourService(Service $service): array
    {
        $description = (string) $service->description;
        $relu = $service->langageClairPublie();

        return [
            'texte' => $relu ? (string) $service->langage_clair : self::rediger($description),
            'relu' => $relu,
            'essentiels' => self::sansVides([
                'Délais' => self::trouver(self::MOTIF_DELAI, $description),
                'Montants' => self::montants($description),
                'Pièces à apporter' => $service->piecesAFournir(),
                'Horaires' => self::lignes((string) $service->horaires),
                'Contacts' => array_values(array_filter([
                    $service->telephone ? 'Téléphone : '.$service->telephone : null,
                    $service->email ? 'E-mail : '.$service->email : null,
                    $service->adresse ? 'Adresse : '.$service->adresse : null,
                ])),
            ]),
        ];
    }

    /**
     * @return array{texte: string, relu: bool, essentiels: list<array{label: string, valeurs: list<string>}>}
     */
    public static function pourAnnonce(Annonce $annonce): array
    {
        $contenu = (string) $annonce->contenu;
        $relu = $annonce->langageClairPublie();
        $toutLeTexte = $contenu."\n".$annonce->consignes;

        return [
            'texte' => $relu ? (string) $annonce->langage_clair : self::rediger($contenu),
            'relu' => $relu,
            'essentiels' => self::sansVides([
                'Quand' => [
                    'Du '.Annonce::heureLisible($annonce->debut).' au '.Annonce::heureLisible($annonce->fin).' (heure de Madagascar)',
                ],
                'Où' => [$annonce->estCiblee() ? 'Quartier '.$annonce->nomQuartier().' uniquement' : 'Toute la ville'],
                'Délais' => self::trouver(self::MOTIF_DELAI, $toutLeTexte),
                'Montants' => self::montants($toutLeTexte),
                'Ce qu’il faut faire' => $annonce->listeConsignes(),
                'Numéros utiles' => self::trouver(self::MOTIF_TELEPHONE, $toutLeTexte),
            ]),
        ];
    }

    private static function rediger(string $texte): string
    {
        return app(RedacteurLangageClair::class)->rediger($texte);
    }

    /**
     * @return list<string>
     */
    private static function montants(string $texte): array
    {
        $montants = self::trouver(self::MOTIF_MONTANT, $texte);

        if (preg_match(self::MOTIF_GRATUIT, $texte) === 1) {
            $montants[] = 'Gratuit';
        }

        return $montants;
    }

    /**
     * Passages trouvés dans le texte, sans doublon, dans l'ordre d'apparition.
     *
     * @return list<string>
     */
    private static function trouver(string $motif, string $texte): array
    {
        preg_match_all($motif, $texte, $resultats);

        return array_values(array_unique(array_map(
            fn (string $passage): string => trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $passage)),
            $resultats[0],
        )));
    }

    /**
     * @return list<string>
     */
    private static function lignes(string $texte): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $texte) ?: []), fn (string $ligne): bool => $ligne !== ''));
    }

    /**
     * @param  array<string, array<int, string>>  $groupes
     * @return list<array{label: string, valeurs: list<string>}>
     */
    private static function sansVides(array $groupes): array
    {
        $essentiels = [];

        foreach ($groupes as $label => $valeurs) {
            if ($valeurs !== []) {
                $essentiels[] = ['label' => $label, 'valeurs' => array_values($valeurs)];
            }
        }

        return $essentiels;
    }
}
