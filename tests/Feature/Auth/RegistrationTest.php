<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), jetonAntiRobot('inscription') + [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    // Nouvel habitant : parcours de prise en main (D12) avant l'espace personnel.
    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('onboarding.show', absolute: false));

    $this->assertAuthenticated();
});

test('après inscription, l\'habitant passe par la prise en main puis retrouve son espace personnel', function () {
    $this->followingRedirects()
        ->post(route('register.store'), jetonAntiRobot('inscription') + [
            'name' => 'Hery Rakoto',
            'email' => 'hery@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertOk()
        ->assertSee('Bienvenue à Nova Terra');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Bonjour Hery Rakoto')
        ->assertSee('hery@example.com')
        ->assertSee('Citoyen');
});

test('un formulaire vide est refusé', function () {
    $this->post(route('register.store'), jetonAntiRobot('inscription') + [])
        ->assertSessionHasErrors(['name', 'email', 'password']);

    $this->assertGuest();
});

test('un e-mail déjà utilisé est refusé avec un message en français', function () {
    app()->setLocale('fr');
    User::factory()->create(['email' => 'pris@example.com']);

    $this->post(route('register.store'), jetonAntiRobot('inscription') + [
        'name' => 'Doublon',
        'email' => 'pris@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors(['email' => 'Cette valeur de adresse e-mail est déjà utilisée.']);

    expect(User::where('email', 'pris@example.com')->count())->toBe(1);
    $this->assertGuest();
});

test('l\'espace personnel n\'affiche que les données du compte connecté', function () {
    $autre = User::factory()->create(['name' => 'Autre Habitant', 'email' => 'autre@example.com']);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee($autre->name)
        ->assertDontSee($autre->email);
});
