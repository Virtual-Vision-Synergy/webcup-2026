<?php

namespace App\Services;

use App\Models\RecommandationCanicule;

/**
 * F31 : implémentation par défaut, sans IA : les modèles de texte en base (profil × niveau), éditables par l'admin.
 */
class RecommandationsEcrites implements RecommandationProvider
{
    public function conseils(string $profil, string $niveau): array
    {
        return RecommandationCanicule::pour($profil, $niveau);
    }
}
