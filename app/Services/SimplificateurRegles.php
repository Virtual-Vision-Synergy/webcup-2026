<?php

namespace App\Services;

use App\Models\ExplicationSimple;
use App\Models\SynonymeSimple;
use App\Support\Lexique;
use Illuminate\Support\Str;

/**
 * F90 : implémentation par défaut, sans IA : l'explication rédigée du passage, les mots difficiles
 * du texte officiel traduits par les synonymes en base, et les définitions du lexique (D13) liées au passage.
 */
class SimplificateurRegles implements Simplificateur
{
    public function expliquer(ExplicationSimple $passage): array
    {
        $texte = ' '.$this->normaliser((string) $passage->texte_officiel).' ';
        $mots = [];

        if (trim($texte) !== '') {
            foreach (SynonymeSimple::query()->orderBy('mot')->get(['mot', 'equivalent']) as $synonyme) {
                $mot = $this->normaliser($synonyme->mot);

                if ($mot !== '' && preg_match('/\b'.preg_quote($mot, '/').'/u', $texte) === 1) {
                    $mots[] = ['mot' => $synonyme->mot, 'equivalent' => $synonyme->equivalent];
                }
            }
        }

        $termes = [];

        foreach ($passage->termes ?? [] as $slug) {
            $terme = Lexique::terme((string) $slug);

            if ($terme !== null) {
                $termes[] = ['slug' => $terme['slug'], 'terme' => $terme['terme'], 'definition' => $terme['definition']];
            }
        }

        return [
            'explication' => $passage->explication,
            'mots' => $mots,
            'termes' => $termes,
        ];
    }

    private function normaliser(string $texte): string
    {
        return Str::lower(Str::ascii($texte));
    }
}
