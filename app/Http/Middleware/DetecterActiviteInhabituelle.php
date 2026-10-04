<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SurveillanceSecurite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F85 : compte les requêtes de chaque compte connecté pour repérer les rafales (script, extraction en masse).
 * Ne bloque jamais : au-delà du seuil, un événement de sécurité est enregistré et l'utilisateur est prévenu.
 */
class DetecterActiviteInhabituelle
{
    public function __construct(private SurveillanceSecurite $surveillance) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->surveillance->compterRequete($user, $request);
        }

        return $next($request);
    }
}
