<?php

use App\Models\Role;
use App\Models\User;
use App\Services\NovaTerraApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Livewire\Livewire;

const CLE_API_TEST = 'cle-secrete-test-9f8e7d';

function reponseNovaTerra(): array
{
    return [
        'session' => [
            'status' => 'active',
            'is_running' => true,
            'current_wave' => 1,
            'visible_requests_count' => 2,
            'next_wave_number' => 2,
            'minutes_until_next_wave' => 42,
        ],
        'requests' => [
            [
                'id' => 1,
                'request_code' => 'D01',
                'requester_name' => 'Haut Conseil de la Ville',
                'requester_type' => 'Institution',
                'message_public' => 'Créer un espace citoyen sécurisé.',
                'difficulty' => 'Facile',
                'difficulty_level' => 1,
                'xp_base' => 250,
                'xp_time_bonus' => 0,
                'xp_total' => 250,
                'xp_available' => 250,
                'arrival_type' => 'debut',
                'wave_number' => null,
                'arrival_time' => '',
                'is_initial' => true,
                'group_name' => 'Socle',
                'sort_order' => 1,
                'is_ai_related' => 0,
                'is_ai_request' => false,
            ],
            [
                'id' => 2,
                'request_code' => 'F22',
                'requester_name' => 'Awa, habitante',
                'requester_type' => 'Citoyen',
                'message_public' => 'Un assistant qui répond à mes questions.',
                'difficulty' => 'Expert',
                'difficulty_level' => 4,
                'xp_base' => 750,
                'xp_time_bonus' => 100,
                'xp_total' => 850,
                'xp_available' => 850,
                'arrival_type' => 'vague',
                'wave_number' => 1,
                'arrival_time' => '02:00:00',
                'is_initial' => false,
                'group_name' => '1 — Premiers habitants',
                'sort_order' => 2,
                'is_ai_related' => 1,
                'is_ai_request' => true,
            ],
        ],
    ];
}

beforeEach(function () {
    Sleep::fake();
    config()->set('services.novaterra.url', 'https://novaterra.test/v1');
    config()->set('services.novaterra.key', CLE_API_TEST);
});

test('un invité est redirigé vers la connexion', function () {
    Http::fake();

    $this->get(route('agent.index'))->assertRedirect(route('login'));

    Http::assertNothingSent();
});

test('un citoyen reçoit un 403 et ne voit pas le lien de l\'espace agent', function () {
    Http::fake();
    $citoyen = User::factory()->citoyen()->create();

    $this->actingAs($citoyen)->get(route('agent.index'))->assertForbidden();
    $this->actingAs($citoyen)->get(route('dashboard'))->assertDontSee('data-test="agent-space-link"', false);

    Http::assertNothingSent();
});

test('un agent voit les demandes de l\'API avec difficulté et arrivée en français', function () {
    Http::fake(['novaterra.test/*' => Http::response(reponseNovaTerra())]);

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.index'))
        ->assertOk()
        ->assertSee(['D01', 'Haut Conseil de la Ville', 'Facile', 'F22', 'Expert', 'Vague 1 · H+2', 'Dès le début'])
        ->assertSee('Vague 2 dans 42 min')
        ->assertDontSee(CLE_API_TEST);
});

test('un administrateur accède à l\'espace agent et voit le lien dans le menu', function () {
    Http::fake(['novaterra.test/*' => Http::response(reponseNovaTerra())]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('agent.index'))->assertOk()->assertSee('D01');
    $this->actingAs($admin)->get(route('dashboard'))->assertSee('data-test="agent-space-link"', false);
});

test('la requête sortante porte la clé dans l\'en-tête et pas dans l\'URL', function () {
    Http::fake(['novaterra.test/*' => Http::response(reponseNovaTerra())]);

    $this->actingAs(User::factory()->agent()->create())->get(route('agent.index'))->assertOk();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://novaterra.test/v1/requests'
        && $request->hasHeader('X-Webcup-Api-Key', CLE_API_TEST));
});

test('les réponses sont mises en cache : deux visites, un seul appel', function () {
    Http::fake(['novaterra.test/*' => Http::response(reponseNovaTerra())]);
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->get(route('agent.index'))->assertOk();
    $this->actingAs($agent)->get(route('agent.index'))->assertOk();

    Http::assertSentCount(1);
});

test('API en erreur 500 avec un cache : données en cache et bandeau en français', function () {
    Http::fake(['novaterra.test/*' => Http::sequence()
        ->push(reponseNovaTerra())
        ->whenEmpty(Http::response('erreur', 500)),
    ]);
    Log::spy();

    $this->travelTo(now()->setDate(2026, 10, 3)->setTime(6, 30));
    app(NovaTerraApiClient::class)->requests();
    Cache::forget(NovaTerraApiClient::FRESH_CACHE_KEY);

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.index'))
        ->assertOk()
        ->assertSee('D01')
        ->assertSee('L\'API Nova Terra ne répond pas. Données affichées du 03/10/2026 à 09:30.', false);

    Log::shouldHaveReceived('warning')->once();
});

test('API en timeout avec un cache : données en cache et bandeau en français', function () {
    Http::fake(['novaterra.test/*' => Http::response(reponseNovaTerra())]);
    app(NovaTerraApiClient::class)->requests();
    Cache::forget(NovaTerraApiClient::FRESH_CACHE_KEY);

    Http::fake(fn () => throw new ConnectionException('Délai dépassé'));

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.index'))
        ->assertOk()
        ->assertSee('F22')
        ->assertSee('data-test="api-stale"', false);
});

test('API en erreur sans cache : page 200 et message en français', function () {
    Http::fake(['novaterra.test/*' => Http::response('erreur', 500)]);

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.index'))
        ->assertOk()
        ->assertSee('L\'API Nova Terra ne répond pas.')
        ->assertSee('data-test="api-unavailable"', false)
        ->assertDontSee('Haut Conseil de la Ville');
});

test('une réponse JSON invalide est traitée comme une panne', function () {
    Http::fake(['novaterra.test/*' => Http::response('<html>maintenance</html>', 200)]);

    $result = app(NovaTerraApiClient::class)->requests();

    expect($result->available)->toBeFalse()
        ->and($result->requests)->toBe([])
        ->and(Cache::has(NovaTerraApiClient::LAST_KNOWN_CACHE_KEY))->toBeFalse();
});

test('sans configuration, aucun appel réseau et page en 200', function () {
    Http::fake();
    config()->set('services.novaterra.key', null);

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.index'))
        ->assertOk()
        ->assertSee('data-test="api-unavailable"', false);

    Http::assertNothingSent();
});

test('le filtre par difficulté est appliqué côté serveur', function () {
    Http::fake(['novaterra.test/*' => Http::response(reponseNovaTerra())]);

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::agent.index')
        ->set('difficulte', '4')
        ->assertSee('F22')
        ->assertDontSee('Haut Conseil de la Ville')
        ->call('resetFilters')
        ->assertSee('Haut Conseil de la Ville');
});

test('le rôle ne peut pas être assigné par assignation de masse', function () {
    $adminId = Role::idFor(Role::ADMIN);

    $user = User::create([
        'name' => 'Pirate',
        'email' => 'pirate@example.com',
        'password' => 'password',
        'role_id' => $adminId,
        'role' => 'admin',
    ]);

    $user->fill(['role_id' => $adminId, 'role' => 'admin'])->save();

    expect($user->fresh()->isAdmin())->toBeFalse()
        ->and($user->fresh()->can('viewAgentSpace'))->toBeFalse();
});
