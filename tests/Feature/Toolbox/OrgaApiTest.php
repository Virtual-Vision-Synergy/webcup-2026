<?php

use App\Services\OrgaApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake();
    config()->set('services.orga.url', 'https://orga.test/api');
    config()->set('services.orga.token', 'jeton-de-test');
});

test('sans URL ou sans jeton, OrgaApi renvoie un tableau vide sans appeler le réseau', function () {
    Http::fake();

    config()->set('services.orga.token', null);
    expect(app(OrgaApi::class)->isConfigured())->toBeFalse()
        ->and(app(OrgaApi::class)->get('/alertes'))->toBe([]);

    config()->set('services.orga.token', 'jeton');
    config()->set('services.orga.url', '');
    expect(app(OrgaApi::class)->get('/alertes'))->toBe([]);

    Http::assertNothingSent();
});

test('OrgaApi appelle l\'URL configurée avec le jeton et renvoie la clé data', function () {
    Http::fake(['orga.test/*' => Http::response(['data' => [['id' => 1], ['id' => 2]]])]);

    expect(app(OrgaApi::class)->get('/alertes', ['page' => 2]))->toBe([['id' => 1], ['id' => 2]]);

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://orga.test/api/alertes')
        && $request->hasHeader('Authorization', 'Bearer jeton-de-test')
        && str_contains($request->url(), 'page=2'));
});

test('OrgaApi renvoie le JSON complet quand il n\'y a pas de clé data', function () {
    Http::fake(['orga.test/*' => Http::response(['nom' => 'Orga', 'version' => 3])]);

    expect(app(OrgaApi::class)->get('/infos'))->toBe(['nom' => 'Orga', 'version' => 3]);
});

test('OrgaApi met les réponses en cache par chemin et paramètres', function () {
    Http::fake(['orga.test/*' => Http::response(['data' => [1]])]);

    app(OrgaApi::class)->get('/alertes');
    app(OrgaApi::class)->get('/alertes');
    app(OrgaApi::class)->get('/alertes', ['page' => 2]);

    Http::assertSentCount(2);
});

test('OrgaApi réessaie puis réussit', function () {
    Http::fake(['orga.test/*' => Http::sequence()
        ->push('erreur', 500)
        ->push(['data' => ['ok']]),
    ]);

    expect(app(OrgaApi::class)->get('/alertes'))->toBe(['ok']);

    Http::assertSentCount(2);
});

test('OrgaApi renvoie un tableau vide après les essais et ne met pas l\'échec en cache', function () {
    Http::fake(['orga.test/*' => Http::sequence()
        ->push('erreur', 500)
        ->push('erreur', 500)
        ->push(['data' => ['rétabli']]),
    ]);

    expect(app(OrgaApi::class)->get('/alertes'))->toBe([])
        ->and(app(OrgaApi::class)->get('/alertes'))->toBe(['rétabli']);

    Http::assertSentCount(OrgaApi::RETRY_TIMES + 1);
});

test('OrgaApi renvoie un tableau vide si la connexion échoue', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

    expect(app(OrgaApi::class)->get('/alertes'))->toBe([]);
});

test('OrgaApi renvoie un tableau vide si la réponse n\'est pas du JSON', function () {
    Http::fake(['orga.test/*' => Http::response('<html>oups</html>', 200)]);

    expect(app(OrgaApi::class)->get('/alertes'))->toBe([]);
});
