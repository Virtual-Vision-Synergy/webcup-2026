<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DefinirLangue
{
    /** Langues proposées (code => libellé dans sa propre langue). Les textes sont dans lang/{code}.json. */
    public const LANGUES = ['fr' => 'Français', 'mg' => 'Malagasy', 'en' => 'English'];

    /** Cookie qui garde la langue choisie sur l'écran de connexion, même après déconnexion (F71). */
    public const COOKIE = 'langue';

    /**
     * Applique la langue choisie (session, sinon compte, sinon cookie) ; le français est la langue de référence :
     * une clé absente du fichier JSON s'affiche telle quelle, donc en français.
     * F71 : la langue choisie avant la connexion est mémorisée sur le compte.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $langue = $request->session()->get('langue') ?? $user?->langue ?? $request->cookie(self::COOKIE);

        if (is_string($langue) && array_key_exists($langue, self::LANGUES)) {
            app()->setLocale($langue);

            if ($user && $user->langue !== $langue) {
                $user->forceFill(['langue' => $langue])->saveQuietly();
            }
        }

        return $next($request);
    }
}
