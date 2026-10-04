<?php

use App\Http\Controllers\Auth\LienConnexionController;
use App\Models\LienConnexion;
use App\Models\User;
use App\Notifications\LienDeConnexion;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    Notification::fake();
});

/**
 * Demande un lien pour l'utilisateur et retourne l'URL signée reçue par e-mail.
 */
function demanderLienDeConnexion(User $user): string
{
    test()->post(route('login-link.store'), ['email' => $user->email])
        ->assertSessionHas('status', LienConnexionController::MESSAGE_ENVOI);

    $url = null;

    Notification::assertSentTo($user, LienDeConnexion::class, function (LienDeConnexion $notification) use (&$url) {
        $url = $notification->url;

        return true;
    });

    return $url;
}

test('la page de connexion propose de recevoir un lien', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Recevoir un lien de connexion')
        ->assertSee(route('login-link.create'));

    $this->get(route('login-link.create'))->assertOk();
});

test('un lien valide connecte l\'utilisateur', function () {
    $user = User::factory()->create();
    $url = demanderLienDeConnexion($user);

    $this->get($url)->assertOk()->assertSee('Me connecter');
    $this->assertGuest();

    $this->post($url)->assertRedirect(route('onboarding.show', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('seule l\'empreinte du jeton est stockée en base', function () {
    $user = User::factory()->create();
    $url = demanderLienDeConnexion($user);

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $lien = LienConnexion::query()->sole();

    expect($lien->token_hash)->not->toBe($query['token'])
        ->and($lien->token_hash)->toBe(hash('sha256', $query['token']))
        ->and($lien->user_id)->toBe($user->id);
});

test('un lien expiré est refusé', function () {
    $user = User::factory()->create();
    $url = demanderLienDeConnexion($user);

    $this->travel(LienConnexion::DUREE_MINUTES + 1)->minutes();

    $this->get($url)->assertRedirect(route('login'))->assertSessionHasErrors(['email' => LienConnexionController::MESSAGE_REFUS]);
    $this->post($url)->assertRedirect(route('login'))->assertSessionHasErrors(['email' => LienConnexionController::MESSAGE_REFUS]);

    $this->assertGuest();
});

test('un lien déjà utilisé est refusé', function () {
    $user = User::factory()->create();
    $url = demanderLienDeConnexion($user);

    $this->post($url);
    $this->assertAuthenticatedAs($user);

    auth()->logout();

    $this->post($url)->assertRedirect(route('login'))->assertSessionHasErrors(['email' => LienConnexionController::MESSAGE_REFUS]);
    $this->assertGuest();
});

test('un lien modifié est refusé', function () {
    $user = User::factory()->create();
    $url = demanderLienDeConnexion($user);

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $lienModifie = str_replace('token='.$query['token'], 'token='.str_repeat('a', 64), $url);
    $autreLien = str_replace('/connexion/lien/'.LienConnexion::query()->sole()->id.'?', '/connexion/lien/999?', $url);

    foreach ([$lienModifie, $autreLien, $url.'x'] as $urlInvalide) {
        $this->post($urlInvalide)->assertRedirect(route('login'))->assertSessionHasErrors(['email' => LienConnexionController::MESSAGE_REFUS]);
    }

    $this->assertGuest();
});

test('un nouveau lien invalide le précédent', function () {
    $user = User::factory()->create();
    $premier = demanderLienDeConnexion($user);

    $this->post(route('login-link.store'), ['email' => $user->email]);

    $this->post($premier)->assertRedirect(route('login'))->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('un e-mail inconnu reçoit le même message, sans envoi', function () {
    $this->post(route('login-link.store'), ['email' => 'inconnu@example.com'])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', LienConnexionController::MESSAGE_ENVOI);

    Notification::assertNothingSent();
    expect(LienConnexion::query()->count())->toBe(0);
});

test('un compte désactivé ne reçoit pas de lien, avec le même message', function () {
    $user = User::factory()->create();
    $user->deactivate();

    $this->post(route('login-link.store'), ['email' => $user->email])
        ->assertSessionHas('status', LienConnexionController::MESSAGE_ENVOI);

    Notification::assertNothingSent();
});

test('les demandes de lien sont limitées', function () {
    $user = User::factory()->create();

    foreach (range(1, 3) as $i) {
        $this->post(route('login-link.store'), ['email' => $user->email])->assertSessionHasNoErrors();
    }

    $this->post(route('login-link.store'), ['email' => $user->email])->assertSessionHasErrors('email');

    Notification::assertSentToTimes($user, LienDeConnexion::class, 3);
});

test('la double authentification reste demandée après le lien', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->withTwoFactor()->create();
    $url = demanderLienDeConnexion($user);

    $this->post($url)
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->id);

    $this->assertGuest();
});

test('un utilisateur déjà connecté ne peut pas utiliser un lien', function () {
    $user = User::factory()->create();
    $url = demanderLienDeConnexion($user);

    $autre = User::factory()->create();

    $this->actingAs($autre)->post($url)->assertRedirect();
    $this->assertAuthenticatedAs($autre);
    expect(LienConnexion::query()->sole()->used_at)->toBeNull();
});
