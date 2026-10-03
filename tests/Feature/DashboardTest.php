<?php

use App\Models\Demarche;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('l\'espace personnel affiche les infos du compte et ses démarches, pas celles des autres', function () {
    $user = User::factory()->create(['name' => 'Hery Rakoto']);
    Demarche::factory()->for($user)->create(['titre' => 'Certificat de résidence']);
    Demarche::factory()->create(['titre' => 'Demande de permis de construire']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Hery Rakoto')
        ->assertSee($user->email)
        ->assertSee('Certificat de résidence')
        ->assertDontSee('Demande de permis de construire');
});

test('l\'espace personnel affiche un état vide sans démarche', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Aucune démarche pour le moment');
});
