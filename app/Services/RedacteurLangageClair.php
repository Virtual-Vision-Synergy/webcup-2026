<?php

namespace App\Services;

/**
 * F89 : propose une version en langage clair d'un texte administratif, quand aucune version relue n'est publiée.
 * Par défaut, uniquement les synonymes en base (RedacteurLangageClairRegles, sans IA ni appel réseau).
 * Une implémentation IA pourra être liée dans AppServiceProvider plus tard.
 */
interface RedacteurLangageClair
{
    public function rediger(string $texte): string;
}
