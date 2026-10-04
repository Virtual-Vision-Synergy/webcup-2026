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
        // F77 : en mode dégradé (surcharge des serveurs), le mode allégé est imposé à tout le monde.
        if (ModeDegrade::actif()) {
            return true;
        }

        $request ??= request();

        // F62 : la version simple va plus loin que le mode allégé, elle l'inclut donc.
        if (VersionSimple::actif($request)) {
            return true;
        }

        $user = $request->user();

        if ($user !== null) {
            return (bool) $user->mode_allege;
        }

        return $request->cookie(self::COOKIE) === '1';
    }
}
