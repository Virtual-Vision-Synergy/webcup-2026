<?php

use App\Models\User;

test('l\'espace connecté propose le sélecteur de langue et reste en français par défaut', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('<html lang="fr"', false)
        ->assertSee(route('langue', 'en'), false)
        ->assertSee('Mon espace');
});

test('en anglais, l\'espace connecté déclare lang="en" et traduit le menu latéral', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('langue', 'en'))
        ->assertRedirect();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('<html lang="en"', false)
        ->assertSee('Citizen area')
        ->assertSee('My procedures');
});

test('une langue non proposée renvoie 404', function () {
    $this->get(route('langue', 'de'))->assertNotFound();
});
