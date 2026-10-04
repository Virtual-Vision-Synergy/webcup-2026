<?php

namespace App\Services;

use App\Models\Signalement;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Repère les signalements ouverts qui parlent du même problème (F75), sans IA :
 * même catégorie + lieu proche (mots du lieu en commun) + mots communs dans la description.
 *
 * Deux signalements sont similaires si leur score (60 % lieu, 40 % description, indice de Jaccard)
 * atteint SEUIL ; les groupes sont formés de proche en proche (si A ~ B et B ~ C, A, B et C sont groupés).
 */
class RegroupementSignalements
{
    public const SEUIL = 0.35;

    /** Mots vides ignorés dans la comparaison. */
    private const MOTS_VIDES = [
        'les', 'des', 'une', 'est', 'sur', 'par', 'pour', 'dans', 'avec', 'sans', 'que', 'qui', 'aux', 'du', 'de', 'la', 'le',
        'en', 'et', 'au', 'un', 'il', 'elle', 'ce', 'cette', 'ces', 'son', 'sa', 'ses', 'pas', 'plus', 'tres', 'tout', 'tous',
        'depuis', 'chez', 'moi', 'nous', 'vous', 'rue', 'avenue', 'boulevard', 'place', 'pres', 'devant', 'face', 'hauteur',
    ];

    /**
     * Groupes d'au moins deux signalements similaires, du plus gros au plus petit
     * (à taille égale : le plus soutenu d'abord). Dans chaque groupe, le plus ancien est en tête.
     *
     * @return Collection<int, Collection<int, Signalement>>
     */
    public function groupes(?string $categorie = null): Collection
    {
        $signalements = Signalement::query()
            ->whereIn('statut', Signalement::STATUTS_OUVERTS)
            ->whereNull('doublon_de_id')
            ->when($categorie !== null && $categorie !== '', fn ($query) => $query->where('categorie', $categorie))
            ->with('user')
            ->withCount('soutiens')
            ->oldest()
            ->oldest('id')
            ->get();

        return $signalements
            ->groupBy('categorie')
            ->flatMap(fn (Collection $memeCategorie): array => $this->regrouper($memeCategorie->values()))
            ->filter(fn (Collection $groupe): bool => $groupe->count() > 1)
            ->sortBy([
                fn (Collection $a, Collection $b): int => $b->count() <=> $a->count(),
                fn (Collection $a, Collection $b): int => $b->sum('soutiens_count') <=> $a->sum('soutiens_count'),
            ])
            ->values();
    }

    /**
     * Groupe (calculé côté serveur) qui contient ce signalement, ou null s'il n'a aucun similaire.
     *
     * @return Collection<int, Signalement>|null
     */
    public function groupeDe(Signalement $signalement): ?Collection
    {
        return $this->groupes($signalement->categorie)
            ->first(fn (Collection $groupe): bool => $groupe->contains('id', $signalement->id));
    }

    public function score(Signalement $a, Signalement $b): float
    {
        if ($a->categorie !== $b->categorie) {
            return 0.0;
        }

        return 0.6 * $this->jaccard($this->mots((string) $a->lieu), $this->mots((string) $b->lieu))
            + 0.4 * $this->jaccard($this->mots((string) $a->description), $this->mots((string) $b->description));
    }

    /**
     * Union-find simple sur une catégorie.
     *
     * @param  Collection<int, Signalement>  $items
     * @return array<int, Collection<int, Signalement>>
     */
    private function regrouper(Collection $items): array
    {
        $parent = range(0, max($items->count() - 1, 0));
        $racine = function (int $i) use (&$parent): int {
            while ($parent[$i] !== $i) {
                $parent[$i] = $parent[$parent[$i]];
                $i = $parent[$i];
            }

            return $i;
        };

        for ($i = 0; $i < $items->count(); $i++) {
            for ($j = $i + 1; $j < $items->count(); $j++) {
                if ($this->score($items[$i], $items[$j]) >= self::SEUIL) {
                    $parent[$racine($j)] = $racine($i);
                }
            }
        }

        $groupes = [];
        foreach ($items as $index => $item) {
            $groupes[$racine($index)][] = $item;
        }

        return array_values(array_map(fn (array $groupe): Collection => collect($groupe), $groupes));
    }

    /**
     * @return array<int, string>
     */
    private function mots(string $texte): array
    {
        $mots = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($texte)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $mots,
            fn (string $mot): bool => mb_strlen($mot) >= 3 && ! in_array($mot, self::MOTS_VIDES, true),
        )));
    }

    /**
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    private function jaccard(array $a, array $b): float
    {
        $union = count(array_unique([...$a, ...$b]));

        return $union === 0 ? 0.0 : count(array_intersect($a, $b)) / $union;
    }
}
