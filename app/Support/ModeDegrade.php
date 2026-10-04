<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * F77 : « Mode dégradé » quand les serveurs sont surchargés.
 * Activé par la variable d'environnement MODE_DEGRADE ou par un admin (page Filament « Mode dégradé »).
 * Il impose le mode allégé (F59) à tout le monde, espace les rafraîchissements automatiques,
 * met en cache navigateur les pages publiques et affiche le bandeau « Service en mode allégé ».
 */
class ModeDegrade
{
    public const CACHE_KEY = 'mode_degrade';

    /** Facteur appliqué aux intervalles de wire:poll en mode dégradé. */
    public const FACTEUR_POLL = 4;

    /** Durée (secondes) de mise en cache navigateur des pages publiques en mode dégradé. */
    public const CACHE_HTTP_SECONDES = 300;

    public static function actif(): bool
    {
        return (bool) config('app.mode_degrade') || self::activeParAdmin();
    }

    public static function activeParAdmin(): bool
    {
        return (bool) Cache::memo()->get(self::CACHE_KEY, false);
    }

    public static function activeParEnvironnement(): bool
    {
        return (bool) config('app.mode_degrade');
    }

    public static function activer(): void
    {
        Cache::memo()->forever(self::CACHE_KEY, true);
    }

    public static function desactiver(): void
    {
        Cache::memo()->forget(self::CACHE_KEY);
    }

    /**
     * Intervalle de rafraîchissement à placer dans wire:poll (ex. « 30s », « 120s » en mode dégradé).
     */
    public static function poll(int $secondes): string
    {
        return (self::actif() ? $secondes * self::FACTEUR_POLL : $secondes).'s';
    }
}
