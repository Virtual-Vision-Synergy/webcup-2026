<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * F62 : « Version simple » des pages : texte, liens et formulaires, sans images décoratives,
 * animations, police web, carte ni scripts de rafraîchissement. Implique le mode allégé (F59).
 * Mémorisée sur le compte de l'habitant, et dans un cookie pour les visiteurs (page d'accueil).
 */
class VersionSimple
{
    public const COOKIE = 'version_simple';

    public static function actif(?Request $request = null): bool
    {
        $request ??= request();
        $user = $request->user();

        if ($user !== null) {
            return (bool) $user->version_simple;
        }

        return $request->cookie(self::COOKIE) === '1';
    }
}
