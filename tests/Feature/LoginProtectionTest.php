<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Notifications\TentativesConnexionSuspectes;
use Illuminate\Support\Facades\Notification;

/**
 * F37 : protection contre les tentatives de connexion abusives.
 */
function echouer(string $email, int $fois = 1, string $ip = '127.0.0.1'): void
{
    foreach (range(1, $fois) as $i) {
        test()->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('login.store'), ['email' => $email, 'password' => 'mauvais-mot-de-passe']);
    }
}

test('après 5 échecs, la 6e tentative est bloquée avec le délai en français', function () {
    $user = User::factory()->citoyen()->create();

    echouer($user->email, 5);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'mauvais-mot-de-passe'])
        ->assertRedirect()
        ->assertSessionHasErrors(['email' => 'Trop de tentatives de connexion. Par sécurité, réessayez dans 15 minutes.']);

    $this->assertGuest();
});

test('le 5e échec annonce déjà le blocage, et les essais restants sont indiqués avant', function () {
    app()->setLocale('fr');
    $user = User::factory()->citoyen()->create();

    echouer($user->email, 2);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'mauvais'])
        ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent à aucun compte. Il vous reste 2 tentatives avant un blocage temporaire.']);

    echouer($user->email);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'mauvais'])
        ->assertSessionHasErrors(['email' => 'Trop de tentatives de connexion. Par sécurité, réessayez dans 15 minutes.']);
});

test('pendant le blocage, le bon mot de passe est refusé ; après expiration, la connexion fonctionne', function () {
    $user = User::factory()->citoyen()->create();

    echouer($user->email, 5);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->travel(16)->minutes();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($user);
});

test('le formulaire garde l’e-mail saisi mais jamais le mot de passe', function () {
    $user = User::factory()->citoyen()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'mauvais'])
        ->assertSessionHasInput('email', $user->email)
        ->assertSessionMissing('_old_input.password');
});

test('2 échecs puis succès : connecté tout de suite et compteur remis à zéro', function () {
    $user = User::factory()->citoyen()->create();

    echouer($user->email, 2);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);

    // Compteur reparti de zéro : 2 + 4 échecs ne bloquent pas, alors que 6 échecs consécutifs bloqueraient.
    auth()->logout();
    echouer($user->email, 4);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($user);
});

test('une connexion correcte du premier coup ne crée aucune entrée d’échec', function () {
    $user = User::factory()->citoyen()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
    expect(LoginAttempt::failed()->count())->toBe(0)
        ->and(LoginAttempt::where('successful', true)->where('user_id', $user->id)->count())->toBe(1);
});

test('le blocage d’un couple e-mail + IP n’empêche pas le titulaire de se connecter depuis un autre réseau', function () {
    $user = User::factory()->citoyen()->create();

    echouer($user->email, 5, '41.188.37.204');

    $this->withServerVariables(['REMOTE_ADDR' => '102.16.44.12'])
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
});

test('une même IP qui essaie de nombreux comptes différents est bloquée', function () {
    $victime = User::factory()->citoyen()->create();

    foreach (range(1, 20) as $i) {
        echouer("compte{$i}@example.com", 1, '41.188.37.204');
    }

    $this->withServerVariables(['REMOTE_ADDR' => '41.188.37.204'])
        ->post(route('login.store'), ['email' => $victime->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Trop de tentatives de connexion. Par sécurité, réessayez dans 10 minutes.']);

    $this->assertGuest();
});

test('un e-mail inexistant reçoit exactement les mêmes messages qu’un compte existant', function () {
    $user = User::factory()->citoyen()->create();

    $messages = function (string $email): array {
        $obtenus = [];
        foreach (range(1, 6) as $i) {
            $this->post(route('login.store'), ['email' => $email, 'password' => 'mauvais']);
            $obtenus[] = session('errors')['default']['messages']['email'][0] ?? null;
        }

        return $obtenus;
    };

    expect($messages('personne@example.com'))->toBe($messages($user->email));
});

test('chaque échec est journalisé sans mot de passe', function () {
    $user = User::factory()->citoyen()->create();

    $this->withHeader('User-Agent', str_repeat('A', 400))
        ->post(route('login.store'), ['email' => strtoupper($user->email), 'password' => 'MotDePasseSecret!42']);

    $attempt = LoginAttempt::sole();

    expect($attempt->email)->toBe($user->email)
        ->and($attempt->user_id)->toBe($user->id)
        ->and($attempt->successful)->toBeFalse()
        ->and($attempt->reason)->toBe(LoginAttempt::REASON_BAD_CREDENTIALS)
        ->and(strlen((string) $attempt->user_agent))->toBe(255)
        ->and(json_encode($attempt->getAttributes()))->not->toContain('MotDePasseSecret');
});

test('les champs réservés envoyés dans la requête de connexion n’influencent pas le journal', function () {
    $user = User::factory()->citoyen()->create();
    $autre = User::factory()->admin()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'mauvais',
        'user_id' => $autre->id,
        'successful' => true,
        'reason' => null,
        'ip' => '8.8.8.8',
    ]);

    $attempt = LoginAttempt::sole();

    expect($attempt->user_id)->toBe($user->id)
        ->and($attempt->successful)->toBeFalse()
        ->and($attempt->reason)->toBe(LoginAttempt::REASON_BAD_CREDENTIALS)
        ->and($attempt->ip)->toBe('127.0.0.1');
});

test('le compte bloqué est notifié une seule fois par heure, avec une IP masquée', function () {
    Notification::fake();
    $user = User::factory()->citoyen()->create();

    echouer($user->email, 8);

    Notification::assertSentToTimes($user, TentativesConnexionSuspectes::class, 1);
    Notification::assertSentTo($user, TentativesConnexionSuspectes::class, function (TentativesConnexionSuspectes $notification) use ($user) {
        $mail = (string) $notification->toMail($user)->render();

        return str_contains($mail, '127.0.x.x') && ! str_contains($mail, '127.0.0.1');
    });

    $this->travel(61)->minutes();
    echouer($user->email, 5);

    Notification::assertSentToTimes($user, TentativesConnexionSuspectes::class, 2);
});

test('aucune notification n’est envoyée pour un e-mail inexistant', function () {
    Notification::fake();

    echouer('personne@example.com', 7);

    Notification::assertNothingSent();
    expect(LoginAttempt::where('reason', LoginAttempt::REASON_LOCKED_OUT)->exists())->toBeTrue();
});

test('le message « compte désactivé » (F34) reste intact et est journalisé sans compter comme un échec', function () {
    $user = User::factory()->citoyen()->deactivated()->create();

    foreach (range(1, 6) as $i) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => EnsureAccountIsActive::MESSAGE]);
    }

    $this->assertGuest();
    expect(LoginAttempt::where('reason', LoginAttempt::REASON_DEACTIVATED)->count())->toBe(6);
});

test('l’inscription et le mot de passe oublié sont limités par IP', function (string $route) {
    foreach (range(1, 5) as $i) {
        $this->post(route($route), ['email' => "test{$i}@example.com"])->assertStatus(302);
    }

    $this->post(route($route), ['email' => 'test6@example.com'])->assertTooManyRequests();
})->with(['register.store', 'password.email']);

test('la page de connexion Filament est désactivée : /admin renvoie vers /login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});
