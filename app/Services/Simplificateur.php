<?php

namespace App\Services;

use App\Models\ExplicationSimple;

/**
 * F90 : produit l'explication simple d'un passage administratif.
 * Par défaut, uniquement des textes rédigés et des règles en base (SimplificateurRegles, sans IA ni appel réseau).
 * Une implémentation IA pourra être liée dans AppServiceProvider plus tard, en gardant les textes rédigés en secours.
 */
interface Simplificateur
{
    /**
     * @return array{explication: string, mots: list<array{mot: string, equivalent: string}>, termes: list<array{slug: string, terme: string, definition: string}>}
     */
    public function expliquer(ExplicationSimple $passage): array;
}
