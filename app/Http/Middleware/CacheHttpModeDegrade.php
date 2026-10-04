<?php

namespace App\Http\Middleware;

use App\Support\ModeDegrade;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F77 : en mode dégradé, les pages publiques consultées par un visiteur non connecté sont gardées
 * en cache par le navigateur (Cache-Control) : un retour sur la page ne recharge plus le serveur.
 * « private » : jamais en cache partagé (proxy), la page contient le jeton CSRF de la session.
 */
class CacheHttpModeDegrade
{
    /** Pages publiques en lecture seule concernées (les alertes restent toujours à jour). */
    public const ROUTES = [
        'home',
        'privacy.show',
        'accessibility.show',
        'projets.index',
        'projets.show',
        'urgences.index',
        'partners.index',
        'partners.show',
        'ideas.index',
        'ideas.show',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            ModeDegrade::actif()
            && $request->isMethod('GET')
            && $request->user() === null
            && $request->routeIs(...self::ROUTES)
            && $response->getStatusCode() === 200
        ) {
            $response->headers->set('Cache-Control', 'private, max-age='.ModeDegrade::CACHE_HTTP_SECONDES);
        }

        return $response;
    }
}
