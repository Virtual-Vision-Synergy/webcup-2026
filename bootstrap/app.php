<?php

use App\Http\Middleware\DefinirLangue;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\MesurerPerformance;
use App\Http\Middleware\SecurityHeaders;
use App\Models\Annonce;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [DefinirLangue::class, EnsureAccountIsActive::class]);
        // F78 : placé en tête pour tout compter ; n'agit qu'hors production (en-tête Server-Timing : requêtes SQL et temps).
        $middleware->web(prepend: [MesurerPerformance::class]);
        // Écrit par le navigateur (bouton « Fermer » du bandeau D18) ; contenu filtré par Annonce::clesFermees().
        $middleware->encryptCookies(except: [Annonce::COOKIE_FERMES]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // F54 : lien « Ce n'était pas moi » altéré ou expiré → 403 avec un message en français.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->routeIs('profile.devices.report*')) {
                return response()->view('errors.lien-appareil-invalide', [], 403);
            }

            return null;
        });

        // F70 : chaque accès refusé (403) d'un utilisateur connecté est journalisé (F47), puis la page 403 s'affiche.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() === 403 && $request->user() !== null) {
                $subject = collect($request->route()?->parameters() ?? [])->first(fn (mixed $valeur): bool => $valeur instanceof Model);
                AuditLogger::logRefus($e->getMessage(), $subject, $request);
            }

            return null;
        });
    })->create();
