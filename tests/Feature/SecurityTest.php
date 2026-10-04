<?php

use App\Models\User;

test('un invité est redirigé vers la connexion', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('un utilisateur normal ne peut pas accéder à l\'admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

test('un admin peut accéder à l\'admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin')->assertOk();
});

test('on ne peut pas devenir admin en trafiquant l\'inscription', function () {
    $this->post('/register', jetonAntiRobot('inscription') + [
        'name' => 'Pirate',
        'email' => 'pirate@test.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'admin',
        'role_id' => 3,
    ]);

    $pirate = User::where('email', 'pirate@test.com')->first();

    expect($pirate)->not->toBeNull()
        ->and($pirate->isAdmin())->toBeFalse();
});

test('la connexion est bloquée après trop de tentatives', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->post('/login', jetonAntiRobot('connexion') + ['email' => $user->email, 'password' => 'mauvais']);
    }

    // F37 : le blocage renvoie vers /login avec un message en français (plus de 429 du middleware throttle).
    $this->post('/login', jetonAntiRobot('connexion') + ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Trop de tentatives de connexion. Par sécurité, réessayez dans 15 minutes.']);

    $this->assertGuest();
});

test('les en-têtes de sécurité sont présents', function () {
    $this->get('/')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});
