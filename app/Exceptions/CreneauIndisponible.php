<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Réservation impossible (créneau pris entre-temps, passé, d'un autre service, chevauchement…).
 * Le message est en français et peut être affiché tel quel à l'habitant.
 */
class CreneauIndisponible extends RuntimeException
{
    public const DEJA_PRIS = 'Ce créneau vient d\'être réservé par quelqu\'un d\'autre. Choisissez-en un autre.';

    public const INVALIDE = 'Ce créneau n\'est plus disponible. Choisissez-en un autre.';

    public const CHEVAUCHEMENT = 'Vous avez déjà un rendez-vous sur cet horaire. Choisissez un autre créneau.';

    public const SERVICE_INDISPONIBLE = 'Ce service est momentanément indisponible : la prise de rendez-vous est suspendue. Consultez sa fiche pour la date de retour prévue ou contactez la mairie.';

    public static function serviceIndisponible(): self
    {
        return new self(self::SERVICE_INDISPONIBLE);
    }

    public static function dejaPris(): self
    {
        return new self(self::DEJA_PRIS);
    }

    public static function invalide(): self
    {
        return new self(self::INVALIDE);
    }

    public static function chevauchement(): self
    {
        return new self(self::CHEVAUCHEMENT);
    }
}
