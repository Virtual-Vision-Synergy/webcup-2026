<?php

namespace App\Services;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * F69 : vérifie la configuration de sécurité de l'environnement courant.
 * Utilisé par la commande « php artisan security:check » et par la page /admin/securite.
 */
class SecurityChecker
{
    /**
     * @return list<array{libelle: string, ok: bool, detail: string}>
     */
    public function verifier(): array
    {
        $entetes = $this->entetes();
        $url = (string) config('app.url');

        return [
            $this->ligne('APP_DEBUG désactivé', ! config('app.debug'), config('app.debug') ? 'APP_DEBUG=true : les traces d’erreur sont visibles.' : 'Aucune trace d’erreur affichée.'),
            $this->ligne('APP_ENV en production', app()->isProduction(), 'Environnement actuel : '.app()->environment()),
            $this->ligne('APP_KEY définie', filled(config('app.key')), filled(config('app.key')) ? 'Clé de chiffrement présente.' : 'Aucune clé : chiffrement impossible.'),
            $this->ligne('HTTPS (APP_URL)', str_starts_with($url, 'https://'), $url),
            $this->ligne('Cookie de session Secure', (bool) config('session.secure'), 'SESSION_SECURE_COOKIE='.(config('session.secure') ? 'true' : 'false')),
            $this->ligne('Cookie de session HttpOnly', (bool) config('session.http_only'), 'Inaccessible au JavaScript.'),
            $this->ligne('Cookie de session SameSite', in_array(config('session.same_site'), ['lax', 'strict'], true), 'SameSite='.(string) config('session.same_site')),
            $this->ligne('En-tête Content-Security-Policy', $entetes->has('Content-Security-Policy'), $entetes->has('Content-Security-Policy') ? 'Appliquée.' : ($entetes->has('Content-Security-Policy-Report-Only') ? 'En mode Report-Only seulement.' : 'Absente.')),
            $this->ligne('En-tête Strict-Transport-Security', $entetes->has('Strict-Transport-Security'), (string) $entetes->get('Strict-Transport-Security', 'Absent.')),
            $this->ligne('En-tête X-Frame-Options', $entetes->get('X-Frame-Options') === 'DENY', (string) $entetes->get('X-Frame-Options', 'Absent.')),
            $this->ligne('En-tête X-Content-Type-Options', $entetes->get('X-Content-Type-Options') === 'nosniff', (string) $entetes->get('X-Content-Type-Options', 'Absent.')),
            $this->ligne('En-tête Referrer-Policy', $entetes->has('Referrer-Policy'), (string) $entetes->get('Referrer-Policy', 'Absent.')),
            $this->ligne('En-tête Permissions-Policy', $entetes->has('Permissions-Policy'), (string) $entetes->get('Permissions-Policy', 'Absent.')),
        ];
    }

    /**
     * En-têtes réellement posés par le middleware, sur une requête HTTPS simulée vers l'accueil.
     */
    private function entetes(): ResponseHeaderBag
    {
        $requete = Request::create('https://'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost').'/', 'GET');

        return (new SecurityHeaders)->handle($requete, fn (): Response => new Response(''))->headers;
    }

    /**
     * @return array{libelle: string, ok: bool, detail: string}
     */
    private function ligne(string $libelle, bool $ok, string $detail): array
    {
        return ['libelle' => $libelle, 'ok' => $ok, 'detail' => $detail];
    }
}
