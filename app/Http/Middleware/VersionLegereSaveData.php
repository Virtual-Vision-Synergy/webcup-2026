<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F96 : la version légère est servie directement quand le navigateur envoie « Save-Data: on »
 * (lu par App\Support\ModeAllege). La réponse dépend donc de cet en-tête : on le signale aux caches.
 */
class VersionLegereSaveData
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->setVary('Save-Data', false);

        return $response;
    }
}
