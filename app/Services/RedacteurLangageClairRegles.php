<?php

namespace App\Services;

use App\Models\SynonymeSimple;
use Illuminate\Support\Str;

/**
 * F89 : implémentation par défaut, sans IA : chaque mot administratif est remplacé par son équivalent simple
 * (synonymes F90, éditables par l'admin dans Filament). Le reste du texte, chiffres compris, est conservé tel quel.
 */
class RedacteurLangageClairRegles implements RedacteurLangageClair
{
    public function rediger(string $texte): string
    {
        if (trim($texte) === '') {
            return '';
        }

        // Les expressions les plus longues d'abord : « pièces justificatives » avant « pièces ».
        $synonymes = SynonymeSimple::query()->get(['mot', 'equivalent'])
            ->sortByDesc(fn (SynonymeSimple $synonyme): int => mb_strlen($synonyme->mot));

        foreach ($synonymes as $synonyme) {
            $mot = trim($synonyme->mot);

            if ($mot === '') {
                continue;
            }

            // La majuscule du mot remplacé est conservée (début de phrase).
            $texte = (string) preg_replace_callback(
                '/(?<![\p{L}\p{N}])'.preg_quote($mot, '/').'(?![\p{L}\p{N}])/iu',
                fn (array $trouve): string => mb_strtoupper(mb_substr($trouve[0], 0, 1)) === mb_substr($trouve[0], 0, 1)
                    ? Str::ucfirst($synonyme->equivalent)
                    : $synonyme->equivalent,
                $texte,
            );
        }

        return $texte;
    }
}
