<?php

use App\Models\User;

/*
 * F44 : le réglage de taille du texte (A / A+ / A++) est disponible dans chaque parcours,
 * y compris sur mobile, sur la connexion et dans l'espace agent.
 */

test('un invité trouve le réglage de taille du texte dans le menu mobile de l’accueil', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Taille du texte')
        ->assertSee('Texte très agrandi (150 %)');
});

test('la page de connexion propose le réglage de taille du texte', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('aria-label="Taille du texte"', false);
});

test('un citoyen connecté trouve le réglage dans son espace', function () {
    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('aria-label="Taille du texte"', false);
});

test('un agent trouve le réglage dans l’espace agent', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.tableau-de-bord'))
        ->assertOk()
        ->assertSee('aria-label="Taille du texte"', false)
        ->assertSee('Taille du texte et contraste');
});
