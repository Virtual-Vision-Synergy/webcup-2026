<?php

use App\Models\User;

test('accueil affiche le nom de la ville et le rôle de la plateforme visibles sans défiler', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSeeText('Mairie de Nova Terra');
    $response->assertSeeText('Vos démarches, les actualités de la ville et le contact');
});

test('accueil contient les boutons d\'accès rapide aux 4 sections', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSeeText('Services');
    $response->assertSeeText('Actualités');
    $response->assertSeeText('Contact');
    $response->assertSeeText('Connexion');
});

test('route services est accessible (authentifié)', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('services.index'));

    $response->assertStatus(200);
    $response->assertSeeText('Services');
});

test('route actualités est accessible (authentifié)', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('actualites.index'));

    $response->assertStatus(200);
    $response->assertSeeText('Actualités');
});

test('route messages est accessible (authentifié)', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('messages.index'));

    $response->assertStatus(200);
    $response->assertSeeText('Messages');
});

test('routes privées redirigent sans authentification', function () {
    $response = $this->get(route('services.index'));

    $response->assertStatus(302);
    $response->assertRedirect(route('login'));
});

test('pages sont en français et responsive', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    // Vérifier qu'il y a des textes français
    $response->assertSeeText('Créer un compte');
    $response->assertSeeText('Mairie de Nova Terra');
    // Vérifier la présence de classes Tailwind responsive (sm:)
    $response->assertSee('sm:');
    // Vérifier pas de texte anglais courant
    $response->assertDontSeeText('Home');
    $response->assertDontSeeText('Contact Us');
});
