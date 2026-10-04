<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * F93 : état des dépendances externes (envoi d'e-mails…). Quand l'une tombe, l'action de l'habitant aboutit quand même
 * et un bandeau explique ce qui ne fonctionne pas, pendant quelques minutes après la dernière panne constatée.
 */
class DependancesExternes
{
    public const EMAIL = 'email';

    /** Durée (secondes) pendant laquelle une panne reste signalée après le dernier échec. */
    public const DUREE_SIGNALEMENT = 600;

    public static function signalerPanne(string $dependance): void
    {
        Cache::put(self::cle($dependance), now()->toIso8601String(), self::DUREE_SIGNALEMENT);
    }

    public static function enPanne(string $dependance): bool
    {
        return Cache::has(self::cle($dependance));
    }

    public static function retablie(string $dependance): void
    {
        Cache::forget(self::cle($dependance));
    }

    private static function cle(string $dependance): string
    {
        return 'dependance_en_panne.'.$dependance;
    }
}
