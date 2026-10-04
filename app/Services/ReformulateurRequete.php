<?php

namespace App\Services;

/**
 * D10 : reformule une demande d'habitant mal formulée en mots exploitables par la recherche.
 * Aucune IA n'est branchée pour l'instant (SansReformulation) ; une implémentation IA (App\Services\Ai)
 * pourra être liée dans AppServiceProvider sans toucher à la recherche. Retourner null = rien à ajouter.
 */
interface ReformulateurRequete
{
    public function reformuler(string $requete): ?string;
}
