<?php

use App\Services\Ai;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.openrouter.key', 'cle-de-test');
    config()->set('services.openrouter.model', 'modele-test:free');
});

function reponseIa(string $texte = 'Bonjour !'): array
{
    return ['choices' => [['message' => ['content' => $texte]]]];
}

test('sans clé, Ai renvoie null sans appeler le réseau', function () {
    Http::fake();
    config()->set('services.openrouter.key', null);

    expect(app(Ai::class)->isConfigured())->toBeFalse()
        ->and(app(Ai::class)->ask('Système', 'Question'))->toBeNull();

    Http::assertNothingSent();
});

test('sans modèle, Ai renvoie null sans appeler le réseau', function () {
    Http::fake();
    config()->set('services.openrouter.model', '');

    expect(app(Ai::class)->ask('Système', 'Question'))->toBeNull();

    Http::assertNothingSent();
});

test('Ai envoie la clé, le modèle et les messages à OpenRouter', function () {
    Http::fake([Ai::ENDPOINT => Http::response(reponseIa('Réponse'))]);

    expect(app(Ai::class)->ask('Tu es utile.', 'Dis bonjour'))->toBe('Réponse');

    Http::assertSent(fn (Request $request) => $request->url() === Ai::ENDPOINT
        && $request->hasHeader('Authorization', 'Bearer cle-de-test')
        && $request['model'] === 'modele-test:free'
        && $request['messages'][0] === ['role' => 'system', 'content' => 'Tu es utile.']
        && $request['messages'][1] === ['role' => 'user', 'content' => 'Dis bonjour']);
});

test('Ai met la réponse en cache (un seul appel pour la même question)', function () {
    Http::fake([Ai::ENDPOINT => Http::response(reponseIa())]);

    app(Ai::class)->ask('Système', 'Question');
    app(Ai::class)->ask('Système', 'Question');
    app(Ai::class)->ask('Système', 'Autre question');

    Http::assertSentCount(2);
});

test('Ai renvoie null sur une erreur HTTP et ne met pas l\'échec en cache', function () {
    Http::fakeSequence(Ai::ENDPOINT)
        ->push(['error' => 'quota'], 429)
        ->push(reponseIa('Enfin'));

    expect(app(Ai::class)->ask('Système', 'Question'))->toBeNull()
        ->and(app(Ai::class)->ask('Système', 'Question'))->toBe('Enfin');
});

test('Ai renvoie null sur une réponse vide ou mal formée', function () {
    Http::fake([Ai::ENDPOINT => Http::response(['inattendu' => true])]);

    expect(app(Ai::class)->ask('Système', 'Question'))->toBeNull();
});

test('Ai renvoie null si la connexion échoue ou dépasse le délai', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

    expect(app(Ai::class)->ask('Système', 'Question'))->toBeNull();
});
