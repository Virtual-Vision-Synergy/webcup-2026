<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * F78 : mesure avant/après des pages (hors production uniquement).
 *
 * Ajoute l'en-tête « Server-Timing » lisible dans l'onglet Réseau du navigateur (F12 → Timing) :
 * nombre de requêtes SQL, temps passé en base et temps total de la page.
 */
class MesurerPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction()) {
            return $next($request);
        }

        $debut = microtime(true);
        $requetes = 0;
        $tempsSql = 0.0;

        DB::listen(function (QueryExecuted $query) use (&$requetes, &$tempsSql): void {
            $requetes++;
            $tempsSql += $query->time;
        });

        $response = $next($request);

        $total = (microtime(true) - $debut) * 1000;

        $response->headers->set('Server-Timing', sprintf(
            'sql;desc="%d requetes SQL";dur=%.1f, app;desc="Page";dur=%.1f',
            $requetes,
            $tempsSql,
            $total,
        ));

        return $response;
    }
}
