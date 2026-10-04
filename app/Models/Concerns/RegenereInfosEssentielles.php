<?php

namespace App\Models\Concerns;

use App\Support\InfosEssentielles;
use Illuminate\Database\Eloquent\Model;

/**
 * F94 : la version statique de la page « Infos essentielles » est régénérée à chaque modification
 * du modèle (état d'un service, interruption déclarée ou rétablie), après l'envoi de la réponse.
 *
 * @mixin Model
 */
trait RegenereInfosEssentielles
{
    public static function bootRegenereInfosEssentielles(): void
    {
        static::saved(fn () => InfosEssentielles::programmerRegeneration());
        static::deleted(fn () => InfosEssentielles::programmerRegeneration());
    }
}
