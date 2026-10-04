<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * F78 : vide les caches des données lues souvent (accueil, services prioritaires) dès que le modèle change.
 *
 * Les pages gardent une durée de vie courte (filet de sécurité) mais n'affichent jamais une donnée périmée
 * après une modification : création, modification ou suppression d'un service ou d'une actualité.
 *
 * @mixin Model
 */
trait ViderCachesPublics
{
    /** Statistiques et aperçus de la page d'accueil (welcome.blade.php). */
    public const CACHE_ACCUEIL = 'landing.etat';

    /** Services mis en avant, proposés sur le tableau de bord de l'habitant. */
    public const CACHE_SERVICES_PRIORITAIRES = 'services.prioritaires';

    public static function bootViderCachesPublics(): void
    {
        static::saved(fn () => self::viderCachesPublics());
        static::deleted(fn () => self::viderCachesPublics());
    }

    public static function viderCachesPublics(): void
    {
        Cache::forget(self::CACHE_ACCUEIL);
        Cache::forget(self::CACHE_SERVICES_PRIORITAIRES);
    }
}
