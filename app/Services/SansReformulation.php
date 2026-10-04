<?php

namespace App\Services;

/**
 * D10 : implémentation par défaut, sans IA : la recherche repose uniquement sur les mots-clés, synonymes et règles en base.
 */
class SansReformulation implements ReformulateurRequete
{
    public function reformuler(string $requete): ?string
    {
        return null;
    }
}
