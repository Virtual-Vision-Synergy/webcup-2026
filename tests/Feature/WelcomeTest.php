<?php

use App\Models\User;

test('l\'accueil répond 200 et affiche le nom de l\'application', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(config('app.name'));
});

test('un invité voit le bouton « Créer un compte »', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Créer un compte')
        ->assertDontSee('Mon espace');
});

test('un utilisateur connecté voit « Mon espace »', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Mon espace')
        ->assertDontSee('Créer un compte');
});
