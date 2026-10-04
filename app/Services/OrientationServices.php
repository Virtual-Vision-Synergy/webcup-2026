<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Support\Str;
use Throwable;

/**
 * D10 : trouve le bon service même avec une demande mal formulée, sans IA.
 *
 * - tolère les fautes de frappe (distance de Levenshtein), les accents manquants, les majuscules et les pluriels ;
 * - comprend les synonymes du langage courant (table mots_cles_service, éditable par l'admin : « poubelle » → propreté) ;
 * - propose « Vouliez-vous dire… » et, sans résultat exact, les services les plus proches : jamais une liste vide.
 *
 * Une reformulation (IA plus tard) peut enrichir la requête via ReformulateurRequete ; si elle échoue, la recherche continue.
 */
class OrientationServices
{
    /** Poids d'une correspondance selon l'endroit où le mot est trouvé. */
    private const POIDS_MOT_CLE = 10;

    private const POIDS_NOM = 8;

    private const POIDS_CATEGORIE = 4;

    private const POIDS_DESCRIPTION = 2;

    /** Mots trop courants pour orienter (« je veux faire une demande pour mon… »). */
    private const MOTS_VIDES = [
        'a', 'au', 'aux', 'avec', 'besoin', 'bonjour', 'ce', 'ces', 'cette', 'comment', 'd', 'dans', 'de', 'des', 'du', 'demande',
        'demander', 'elle', 'en', 'est', 'et', 'faire', 'faut', 'il', 'j', 'je', 'l', 'la', 'le', 'les', 'leur', 'ma', 'me', 'merci',
        'mes', 'mon', 'moi', 'ne', 'nous', 'obtenir', 'on', 'ou', 'par', 'pas', 'plus', 'pour', 'qu', 'que', 'qui', 'quoi', 's', 'sa',
        'se', 'ses', 'son', 'sur', 'svp', 'ta', 'te', 'tes', 'ton', 'toi', 'tu', 'un', 'une', 'veux', 'voudrais', 'vous', 'y',
        'ai', 'avoir', 'etre', 'suis', 'peux', 'peut', 'veut', 'souhaite', 'aller', 'ici', 'cela', 'ca', 'chez', 'notre', 'votre',
    ];

    public function __construct(private ReformulateurRequete $reformulateur) {}

    /**
     * @return array{resultats: list<array{id: int, score: int, raisons: list<string>}>, exact: bool, suggestion: string|null}
     */
    public function rechercher(string $requete): array
    {
        $mots = self::mots($requete);
        $reformulation = $this->reformulation($requete);

        if ($reformulation !== null) {
            $mots = array_values(array_unique([...$mots, ...self::mots($reformulation)]));
        }

        if ($mots === []) {
            return ['resultats' => [], 'exact' => false, 'suggestion' => null];
        }

        $index = $this->index();
        $vocabulaire = [];

        foreach ($index as $termes) {
            foreach ($termes['entrees'] as $entree) {
                foreach ($entree['mots'] as $mot) {
                    $vocabulaire[$mot] ??= $entree['libelle'];
                }
            }
        }

        $resultats = [];
        $exact = false;

        foreach ($index as $id => $service) {
            $score = 0;
            $raisons = [];

            foreach ($mots as $mot) {
                $meilleur = 0;
                $raison = null;

                foreach ($service['entrees'] as $entree) {
                    foreach ($entree['mots'] as $terme) {
                        $points = match (true) {
                            $terme === $mot => $entree['poids'],
                            self::proche($mot, $terme) => intdiv($entree['poids'] * 6, 10),
                            default => 0,
                        };

                        if ($points > $meilleur) {
                            $meilleur = $points;
                            $raison = $entree['libelle'];
                            $exact = $exact || $terme === $mot;
                        }
                    }
                }

                $score += $meilleur;

                if ($raison !== null && $meilleur >= self::POIDS_CATEGORIE) {
                    $raisons[] = $raison;
                }
            }

            // Expression complète (« acte de naissance ») : bonus.
            foreach ($service['entrees'] as $entree) {
                if (count($entree['mots']) > 1 && array_diff($entree['mots'], $mots) === []) {
                    $score += $entree['poids'];
                }
            }

            if ($score > 0) {
                $resultats[] = ['id' => $id, 'score' => $score, 'raisons' => array_values(array_unique($raisons))];
            }
        }

        if ($resultats === []) {
            $resultats = $this->plusProches($mots, $index);
        }

        usort($resultats, fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: $a['id'] <=> $b['id']);

        return [
            'resultats' => $resultats,
            'exact' => $exact,
            // Proposée seulement si aucun mot n'a été reconnu tel quel.
            'suggestion' => $exact ? null : $this->suggestion($requete, $vocabulaire),
        ];
    }

    /**
     * Mots utiles d'un texte : minuscules, sans accents ni ponctuation, sans mots vides, au singulier.
     *
     * @return list<string>
     */
    public static function mots(string $texte): array
    {
        $normalise = trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($texte))));

        if ($normalise === '') {
            return [];
        }

        $mots = [];

        foreach (explode(' ', $normalise) as $mot) {
            if (in_array($mot, self::MOTS_VIDES, true) || strlen($mot) < 2) {
                continue;
            }

            $mots[] = self::singulier($mot);
        }

        return array_values(array_unique($mots));
    }

    private static function singulier(string $mot): string
    {
        if (strlen($mot) > 4 && preg_match('/[^s][sx]$/', $mot)) {
            return substr($mot, 0, -1);
        }

        return $mot;
    }

    /**
     * Faute de frappe tolérée : 1 lettre d'écart (2 pour les mots longs), ou début de mot (« constru » → construction).
     */
    private static function proche(string $mot, string $terme): bool
    {
        $ecart = self::ecartTolere($mot);

        if ($ecart === 0) {
            return false;
        }

        if (str_starts_with($terme, $mot)) {
            return true;
        }

        return abs(strlen($mot) - strlen($terme)) <= $ecart && levenshtein($mot, $terme) <= $ecart;
    }

    /**
     * Nombre de fautes tolérées : aucune sous 5 lettres (trop de faux amis), 1 jusqu'à 7, 2 au-delà.
     */
    private static function ecartTolere(string $mot): int
    {
        $longueur = strlen($mot);

        return match (true) {
            $longueur >= 8 => 2,
            $longueur >= 5 => 1,
            default => 0,
        };
    }

    /**
     * Mots indexés par service : mots-clés (synonymes de l'admin), nom, catégorie, description.
     *
     * @return array<int, array{entrees: list<array{mots: list<string>, poids: int, libelle: string}>}>
     */
    private function index(): array
    {
        $index = [];

        $services = Service::query()
            ->with('motsCles:id,service_id,mot')
            ->get(['id', 'nom', 'categorie', 'description']);

        foreach ($services as $service) {
            $entrees = [];

            foreach ($service->motsCles as $motCle) {
                $entrees[] = ['mots' => self::mots($motCle->mot), 'poids' => self::POIDS_MOT_CLE, 'libelle' => $motCle->mot];
            }

            $entrees[] = ['mots' => self::mots($service->nom), 'poids' => self::POIDS_NOM, 'libelle' => $service->nom];

            if ($service->categorie !== null) {
                $libelle = (string) Service::labelCategorie($service->categorie);
                $entrees[] = ['mots' => self::mots($libelle.' '.$service->categorie), 'poids' => self::POIDS_CATEGORIE, 'libelle' => $libelle];
            }

            foreach (self::mots((string) $service->description) as $mot) {
                if (strlen($mot) >= 4) {
                    $entrees[] = ['mots' => [$mot], 'poids' => self::POIDS_DESCRIPTION, 'libelle' => $mot];
                }
            }

            $index[$service->id] = ['entrees' => array_values(array_filter($entrees, fn (array $e): bool => $e['mots'] !== []))];
        }

        return $index;
    }

    /**
     * Aucun mot reconnu : les services dont le vocabulaire ressemble le plus à la demande (similarité de lettres).
     *
     * @param  list<string>  $mots
     * @param  array<int, array{entrees: list<array{mots: list<string>, poids: int, libelle: string}>}>  $index
     * @return list<array{id: int, score: int, raisons: list<string>}>
     */
    private function plusProches(array $mots, array $index): array
    {
        $proches = [];

        foreach ($index as $id => $service) {
            $meilleur = 0.0;

            foreach ($mots as $mot) {
                foreach ($service['entrees'] as $entree) {
                    foreach ($entree['mots'] as $terme) {
                        similar_text($mot, $terme, $pourcentage);
                        $meilleur = max($meilleur, $pourcentage);
                    }
                }
            }

            if ($meilleur >= 65) {
                $proches[] = ['id' => $id, 'score' => (int) round($meilleur / 10), 'raisons' => []];
            }
        }

        usort($proches, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        if ($proches !== []) {
            return array_slice($proches, 0, 3);
        }

        // Rien de ressemblant : l'accueil et les services mis en avant, qui orientent vers le bon interlocuteur.
        $ids = Service::query()
            ->where(fn ($query) => $query->where('mis_en_avant', true)->orWhere('nom', 'like', 'Accueil%'))
            ->orderByRaw("case when nom like 'Accueil%' then 0 else 1 end")
            ->limit(3)
            ->pluck('id')
            ->all();

        // Score décroissant : l'accueil reste en tête après le tri par pertinence.
        return array_map(fn (int $id, int $rang): array => ['id' => $id, 'score' => 3 - $rang, 'raisons' => []], array_values($ids), array_keys(array_values($ids)));
    }

    /**
     * « Vouliez-vous dire… » : la phrase de l'habitant, où chaque mot inconnu est remplacé par le mot connu le plus proche.
     *
     * @param  array<string, string>  $vocabulaire
     */
    private function suggestion(string $requete, array $vocabulaire): ?string
    {
        $phrase = [];
        $change = false;

        foreach (preg_split('/\s+/u', trim($requete)) ?: [] as $brut) {
            $mots = self::mots($brut);
            $mot = $mots[0] ?? null;
            $correction = count($mots) === 1 && $mot !== null && ! isset($vocabulaire[$mot]) ? self::plusProcheConnu($mot, $vocabulaire) : null;

            $phrase[] = $correction !== null ? self::libelleSimple($correction, $vocabulaire) : Str::lower($brut);
            $change = $change || $correction !== null;
        }

        return $change ? implode(' ', $phrase) : null;
    }

    /**
     * @param  array<string, string>  $vocabulaire
     */
    private static function plusProcheConnu(string $mot, array $vocabulaire): ?string
    {
        $ecart = self::ecartTolere($mot);
        $meilleur = null;
        $distance = PHP_INT_MAX;

        if ($ecart === 0) {
            return null;
        }

        foreach (array_keys($vocabulaire) as $terme) {
            if (abs(strlen($terme) - strlen($mot)) > $ecart) {
                continue;
            }

            $d = levenshtein($mot, $terme);

            if ($d < $distance && $d <= $ecart) {
                [$meilleur, $distance] = [$terme, $d];
            }
        }

        return $meilleur;
    }

    /**
     * Forme lisible d'un mot du vocabulaire (avec accents si un mot-clé d'un seul mot la fournit).
     *
     * @param  array<string, string>  $vocabulaire
     */
    private static function libelleSimple(string $mot, array $vocabulaire): string
    {
        $libelle = $vocabulaire[$mot] ?? $mot;

        return self::mots($libelle) === [$mot] ? Str::lower($libelle) : $mot;
    }

    private function reformulation(string $requete): ?string
    {
        try {
            $reformulation = $this->reformulateur->reformuler($requete);
        } catch (Throwable $e) {
            // Service de reformulation indisponible : la recherche par mots-clés suffit.
            report($e);

            return null;
        }

        return is_string($reformulation) && trim($reformulation) !== '' ? $reformulation : null;
    }
}
