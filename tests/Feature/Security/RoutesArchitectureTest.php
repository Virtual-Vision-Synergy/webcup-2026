<?php

use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;

/*
| F69 : toute route est protégée par « auth » sauf celles de cette liste blanche, chacune justifiée.
| Une nouvelle route publique doit être une DÉCISION : l'ajouter ici avec sa justification.
*/
const ROUTES_PUBLIQUES = [
    '/' => 'Accueil public de la mairie',
    'vos-donnees' => 'Information RGPD, lisible avant inscription',
    'langue/{code}' => 'Choix de langue (liste blanche, throttle:30,1)',
    'connexion/lien' => 'Demande de lien de connexion (guest, limité par e-mail + IP)',
    'connexion/lien/{lien}' => 'Lien de connexion signé à usage unique (guest)',
    'activer' => 'Activation d’un compte par code (guest, throttle)',
    'appareils/{knownDevice}/signaler' => 'Lien signé « Ce n’était pas moi » reçu par e-mail (signed + throttle)',
    'alertes/{annonce}' => 'Alerte publique en cours de diffusion (Policy view : 404 hors période)',
    'urgences' => 'Numéros d’urgence, lecture seule',
    'up' => 'Sonde de disponibilité',
    'login' => 'Fortify (guest)',
    'logout' => 'Fortify : déconnexion (POST + CSRF)',
    'register' => 'Fortify : inscription (guest, throttle:auth-sensible)',
    'forgot-password' => 'Fortify (guest, throttle:auth-sensible)',
    'reset-password' => 'Fortify (guest, jeton)',
    'reset-password/{token}' => 'Fortify (guest, jeton)',
    'two-factor-challenge' => 'Fortify : second facteur après mot de passe (throttle:two-factor)',
    'storage/{path}' => 'Disque local « serve » : URL signées temporaires uniquement',
];

/** Préfixes techniques des paquets (assets, endpoints Livewire qui revérifient chaque action). */
const PREFIXES_TECHNIQUES = ['livewire', 'flux/', 'filament/', '_boost', 'sanctum/'];

function routeEstAuthentifiee(RouteDefinition $route): bool
{
    return collect($route->gatherMiddleware())->contains(fn (mixed $middleware): bool => is_string($middleware)
        && ($middleware === 'auth' || str_starts_with($middleware, 'auth:') || $middleware === FilamentAuthenticate::class));
}

test('F69 : toute route est authentifiée ou figure dans la liste blanche des routes publiques', function () {
    $nonProtegees = collect(Route::getRoutes()->getRoutes())
        ->reject(fn (RouteDefinition $route): bool => routeEstAuthentifiee($route))
        ->map(fn (RouteDefinition $route): string => $route->uri())
        ->reject(fn (string $uri): bool => array_key_exists($uri, ROUTES_PUBLIQUES))
        ->reject(fn (string $uri): bool => str($uri)->startsWith(PREFIXES_TECHNIQUES))
        ->unique()
        ->values()
        ->all();

    expect($nonProtegees)->toBe([]);
});

test('F69 : les routes de l’espace agent et de l’admin exigent un rôle en plus de la connexion', function () {
    $routes = collect(Route::getRoutes()->getRoutes());

    $routes->filter(fn (RouteDefinition $route): bool => str_starts_with($route->uri(), 'agent'))
        ->each(fn (RouteDefinition $route) => expect($route->gatherMiddleware())->toContain('can:viewAgentSpace'));

    $routes->filter(fn (RouteDefinition $route): bool => $route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/'))
        ->each(fn (RouteDefinition $route) => expect(routeEstAuthentifiee($route))->toBeTrue());
});
