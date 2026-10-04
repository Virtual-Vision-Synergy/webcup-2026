<?php

namespace App\Services;

/**
 * F31 : fournit les conseils canicule d'un profil pour un niveau d'alerte.
 * Par défaut, uniquement les textes écrits de l'Agence sanitaire (RecommandationsEcrites, sans IA ni appel réseau).
 * Une implémentation IA pourra être liée dans AppServiceProvider plus tard, en gardant les textes écrits en secours.
 */
interface RecommandationProvider
{
    /**
     * @return list<string>
     */
    public function conseils(string $profil, string $niveau): array;
}
