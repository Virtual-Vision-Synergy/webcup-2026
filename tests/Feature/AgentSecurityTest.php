<?php

use App\Auth\LoginThrottle;
use App\Models\ActionLog;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Livewire\Livewire;

/**
 * F37 : écran agent « Sécurité des connexions » (/agent/securite/connexions).
 */
function requeteConnexion(string $email, string $ip): Request
{
    return Request::create('/login', 'POST', ['email' => $email], server: ['REMOTE_ADDR' => $ip]);
}

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('agent.security.index'))->assertRedirect(route('login'));
});

test('un citoyen reçoit un 403 sur l’écran et sur « Débloquer »', function () {
    $citoyen = User::factory()->citoyen()->create();
    $attempt = LoginAttempt::factory()->lockedOut()->create();

    $this->actingAs($citoyen)->get(route('agent.security.index'))->assertForbidden();

    Livewire::actingAs($citoyen)->test('pages::agent.security.index')->assertForbidden();
    expect($citoyen->can('unlock', $attempt))->toBeFalse();
});

test('un agent voit les tentatives échouées, mais pas les connexions réussies', function () {
    $agent = User::factory()->agent()->create();
    $citoyen = User::factory()->citoyen()->create();
    LoginAttempt::factory()->forUser($citoyen)->create(['ip' => '41.188.37.204']);
    LoginAttempt::factory()->create(['email' => 'inconnu@example.com']);
    LoginAttempt::factory()->successful()->create(['email' => 'reussie@example.com']);

    $this->actingAs($agent)->get(route('agent.security.index'))
        ->assertOk()
        ->assertSee([$citoyen->email, 'inconnu@example.com', '41.188.37.204', 'Existant', 'Inconnu'])
        ->assertDontSee('reussie@example.com')
        ->assertDontSee('Débloquer');
});

test('une IP ayant visé plusieurs comptes et les blocages sont mis en évidence', function () {
    $agent = User::factory()->agent()->create();
    foreach (['a@example.com', 'b@example.com', 'c@example.com'] as $email) {
        LoginAttempt::factory()->create(['email' => $email, 'ip' => '41.188.37.204']);
    }
    LoginAttempt::factory()->lockedOut()->count(2)->create(['email' => 'user@example.com', 'ip' => '102.16.44.12']);

    Livewire::actingAs($agent)->test('pages::agent.security.index')
        ->assertSee('Plusieurs comptes')
        ->assertSee('Bloquée (trop d’essais)')
        ->assertSeeInOrder(['Blocages (24 h)', '1', 'couple(s) e-mail + IP bloqué(s)']);
});

test('les filtres par e-mail, IP, motif et période restreignent la liste', function () {
    $agent = User::factory()->agent()->create();
    LoginAttempt::factory()->create(['email' => 'hanitra@example.com', 'ip' => '10.0.0.1']);
    LoginAttempt::factory()->lockedOut()->create(['email' => 'lucas@example.com', 'ip' => '10.0.0.2']);
    LoginAttempt::factory()->create(['email' => 'ancien@example.com', 'created_at' => now()->subDays(3)]);

    $page = Livewire::actingAs($agent)->test('pages::agent.security.index');
    $liste = fn (): array => $page->instance()->items->pluck('email')->sort()->values()->all();

    expect($liste())->toBe(['hanitra@example.com', 'lucas@example.com']);

    $page->set('email', 'hanitra');
    expect($liste())->toBe(['hanitra@example.com']);

    $page->call('resetFilters')->set('ip', '10.0.0.2');
    expect($liste())->toBe(['lucas@example.com']);

    $page->call('resetFilters')->set('motif', LoginAttempt::REASON_LOCKED_OUT);
    expect($liste())->toBe(['lucas@example.com']);

    $page->call('resetFilters')->set('periode', '7j');
    expect($liste())->toBe(['ancien@example.com', 'hanitra@example.com', 'lucas@example.com']);

    // Les jokers SQL saisis sont traités comme du texte.
    $page->set('email', '%');
    expect($liste())->toBe([]);
});

test('un agent reçoit un 403 sur « Débloquer »', function () {
    $agent = User::factory()->agent()->create();
    $attempt = LoginAttempt::factory()->lockedOut()->create();

    Livewire::actingAs($agent)->test('pages::agent.security.index')
        ->call('unlock', $attempt->id)
        ->assertForbidden();
});

test('un admin peut débloquer un couple e-mail + IP', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->citoyen()->create();
    $attempt = LoginAttempt::factory()->forUser($user)->lockedOut()->create(['ip' => '102.16.44.12']);

    $throttle = app(LoginThrottle::class);
    $requete = requeteConnexion($user->email, '102.16.44.12');
    foreach (range(1, 5) as $i) {
        $throttle->increment($requete);
    }
    expect($throttle->tooManyAttempts($requete))->toBeTrue();

    Livewire::actingAs($admin)->test('pages::agent.security.index')
        ->assertSee('Débloquer')
        ->call('unlock', $attempt->id)
        ->assertOk();

    expect($throttle->tooManyAttempts($requete))->toBeFalse()
        ->and(ActionLog::where('action', 'login_unlocked')->where('user_id', $admin->id)->exists())->toBeTrue();
});

test('l’e-mail saisi par un attaquant est échappé à l’affichage', function () {
    $agent = User::factory()->agent()->create();
    LoginAttempt::factory()->create(['email' => '<script>alert(1)</script>@x.io']);

    $this->actingAs($agent)->get(route('agent.security.index'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false);
});

test('le journal est purgé au-delà de la durée de rétention', function () {
    LoginAttempt::factory()->create(['created_at' => now()->subDays(31)]);
    $recente = LoginAttempt::factory()->create(['created_at' => now()->subDays(2)]);

    $this->artisan('model:prune', ['--model' => [LoginAttempt::class]])->assertSuccessful();

    expect(LoginAttempt::pluck('id')->all())->toBe([$recente->id]);
});
