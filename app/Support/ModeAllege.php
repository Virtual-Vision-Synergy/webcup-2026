<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * F59 : « Mode allégé » pour les connexions lentes (sans images décoratives ni animations).
 * Mémorisé sur le compte de l'habitant, et dans un cookie pour les visiteurs (page d'accueil, connexion).
 */
class ModeAllege
{
    public const COOKIE = 'mode_allege';

    public static function actif(?Request $request = null): bool
    {
        $request ??= request();
        $user = $request->user();

        if ($user !== null) {
            return (bool) $user->mode_allege;
        }

        return $request->cookie(self::COOKIE) === '1';
    }
}
