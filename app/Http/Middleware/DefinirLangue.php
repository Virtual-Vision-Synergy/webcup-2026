<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DefinirLangue
{
    /** Langues proposées (code => libellé dans sa propre langue). Les textes sont dans lang/{code}.json. */
    public const LANGUES = ['fr' => 'Français', 'en' => 'English', 'mg' => 'Malagasy'];

    /**
     * Applique la langue choisie par le visiteur (session) ; le français est la langue de référence :
     * une clé absente du fichier JSON s'affiche telle quelle, donc en français.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $langue = $request->session()->get('langue');

        if (is_string($langue) && array_key_exists($langue, self::LANGUES)) {
            app()->setLocale($langue);
        }

        return $next($request);
    }
}
