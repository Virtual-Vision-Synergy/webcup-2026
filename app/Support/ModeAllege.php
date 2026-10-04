<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * F59 : « Mode allégé » pour les connexions lentes (sans images décoratives ni animations).
 * Mémorisé sur le compte de l'habitant, et dans un cookie pour les visiteurs (page d'accueil, connexion).
 *
 * F96 : activé automatiquement (en-tête Save-Data, ou connexion lente / petit écran détectés par le navigateur),
 * sauf si l'habitant a fait un choix explicite, qui prime toujours.
 */
class ModeAllege
{
    public const COOKIE = 'mode_allege';

    /** F96 : écrit par le navigateur (détection de la connexion), non chiffré ; seule la valeur « 1 » est lue. */
    public const COOKIE_AUTO = 'mode_allege_auto';

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

        if ($request->user()?->mode_allege) {
            return true;
        }

        $choix = self::choixExplicite($request);

        if ($choix !== null) {
            return $choix;
        }

        return self::detecte($request);
    }

    /**
     * F96 : version légère activée par la détection automatique (et non par un choix de l'habitant).
     */
    public static function automatique(?Request $request = null): bool
    {
        $request ??= request();

        return ! ModeDegrade::actif()
            && ! VersionSimple::actif($request)
            && ! $request->user()?->mode_allege
            && self::choixExplicite($request) === null
            && self::detecte($request);
    }

    /**
     * F96 : la détection automatique peut-elle encore s'appliquer (aucun choix ni mode imposé) ?
     */
    public static function detectionPossible(?Request $request = null): bool
    {
        $request ??= request();

        return ! ModeDegrade::actif()
            && ! VersionSimple::actif($request)
            && ! $request->user()?->mode_allege
            && self::choixExplicite($request) === null;
    }

    /**
     * Choix mémorisé par la bascule (route « mode-allege ») : true, false, ou null si jamais choisi.
     */
    public static function choixExplicite(Request $request): ?bool
    {
        return match ($request->cookie(self::COOKIE)) {
            '1' => true,
            '0' => false,
            default => null,
        };
    }

    /**
     * F96 : en-tête « Save-Data: on » (économiseur de données du navigateur) ou détection côté navigateur.
     */
    public static function detecte(Request $request): bool
    {
        return strtolower(trim((string) $request->header('Save-Data'))) === 'on'
            || $request->cookie(self::COOKIE_AUTO) === '1';
    }
}
