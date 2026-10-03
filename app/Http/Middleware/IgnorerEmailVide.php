<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F71 : à l'inscription, l'e-mail est facultatif. Un champ vide est retiré de la requête avant Fortify
 * (qui met l'e-mail en minuscules dès que le champ est présent, même vide).
 */
class IgnorerEmailVide
{
    public function handle(Request $request, Closure $next): Response
    {
        if (blank($request->input('email'))) {
            $request->request->remove('email');
        }

        return $next($request);
    }
}
