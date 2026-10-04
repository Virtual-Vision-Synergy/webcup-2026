<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * F34 : un compte désactivé pendant qu'il était connecté est déconnecté à la requête suivante.
 * F85 : idem pour un compte verrouillé temporairement (par un admin ou par cumul de signaux suspects).
 */
class EnsureAccountIsActive
{
    public const MESSAGE = 'Votre compte a été désactivé. Contactez la mairie de Nova Terra pour le réactiver.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && (! $user->isActive() || $user->estVerrouille())) {
            $message = $user->isActive() ? $user->messageVerrouillage() : self::MESSAGE;

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(403, $message);
            }

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
