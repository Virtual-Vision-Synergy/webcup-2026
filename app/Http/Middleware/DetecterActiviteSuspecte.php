<?php

namespace App\Http\Middleware;

use App\Services\SecurityMonitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F69 : bloque temporairement (429) un compte ou une IP après trop d'événements suspects,
 * et journalise les motifs d'attaque évidents dans l'URL (« ../ », « <script »).
 * Un motif isolé est seulement journalisé : seule l'accumulation bloque.
 */
class DetecterActiviteSuspecte
{
    public function handle(Request $request, Closure $next): Response
    {
        if (SecurityMonitor::estBloque($request)) {
            $minutes = (int) config('security.suspect.block_minutes');

            return response()->view('errors.activite-suspecte', ['minutes' => $minutes], 429, ['Retry-After' => (string) ($minutes * 60)]);
        }

        if (SecurityMonitor::contientMotif($request)) {
            SecurityMonitor::signaler(SecurityMonitor::MOTIF, $request);
        }

        return $next($request);
    }
}
