<?php

use App\Models\User;

/*
| F69 (A11, A12) : en-têtes de sécurité, CSP à nonce, cookies, CSRF et commande security:check.
*/

test('les en-têtes de sécurité sont posés sur une page publique et sur une page connectée', function (bool $connecte) {
    if ($connecte) {
        $this->actingAs(User::factory()->create());
    }

    $reponse = $this->get($connecte ? route('dashboard') : '/')->assertOk();

    $reponse->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy');

    expect($reponse->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]+'/")
        ->not->toContain("script-src 'self' 'unsafe-inline'");
})->with(['page publique' => false, 'page connectée' => true]);

test('chaque script en ligne de la page porte le nonce de la CSP', function () {
    $reponse = $this->get('/')->assertOk();
    preg_match("/'nonce-([A-Za-z0-9]+)'/", (string) $reponse->headers->get('Content-Security-Policy'), $nonce);

    preg_match_all('/<script(?![^>]*\bsrc=)([^>]*)>/', $reponse->getContent(), $scriptsEnLigne);

    expect($scriptsEnLigne[1])->not->toBeEmpty();
    foreach ($scriptsEnLigne[1] as $attributs) {
        expect($attributs)->toContain('nonce="'.$nonce[1].'"');
    }
});

test('HSTS est envoyé uniquement sur une connexion HTTPS', function () {
    $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('la CSP passe en mode Report-Only quand la variable de secours est activée', function () {
    config(['security.csp.report_only' => true]);

    $this->get('/')
        ->assertHeaderMissing('Content-Security-Policy')
        ->assertHeader('Content-Security-Policy-Report-Only');
});

test('le cookie de session est HttpOnly et SameSite=Lax', function () {
    $cookie = collect($this->get('/')->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === config('session.cookie'));

    expect($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax');
});

test('un formulaire envoyé sans jeton CSRF est refusé avec 419', function () {
    // Hors environnement « testing » : sinon Laravel désactive volontairement la vérification CSRF dans les tests.
    $this->app['env'] = 'local';

    $this->actingAs(User::factory()->create())
        ->post(route('notifications.read-all'))
        ->assertStatus(419);
});

test('security:check signale en KO un environnement de démonstration (APP_DEBUG=true, local)', function () {
    config(['app.debug' => true]);

    $this->artisan('security:check')
        ->expectsOutputToContain('APP_DEBUG désactivé')
        ->expectsOutputToContain('vérification(s) en échec')
        ->assertFailed();
});

test('security:check est entièrement OK avec la configuration de production', function () {
    $this->app['env'] = 'production';
    config(['app.debug' => false, 'app.url' => 'https://virtualvisionsy.madagascar.webcup.hodi.cloud', 'session.secure' => true]);

    $this->artisan('security:check')
        ->expectsOutputToContain('Toutes les vérifications de sécurité sont OK.')
        ->assertSuccessful();
});
