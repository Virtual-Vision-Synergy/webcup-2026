<?php

use App\Models\Service;
use App\Models\User;
use Database\Seeders\ServiceTranslationSeeder;
use Livewire\Livewire;

/*
| D14 / F27 — Choix de la langue (interface) et traductions des fiches de services, avec repli sur le français.
*/

test('changer de langue met à jour le seul compte connecté et revient sur la page d\'origine', function () {
    $habitant = User::factory()->citoyen()->create();
    $autre = User::factory()->citoyen()->create();

    $this->actingAs($habitant)
        ->from(route('services.index'))
        ->post(route('langue', 'en'))
        ->assertRedirect(route('services.index'));

    expect($habitant->fresh()->langue)->toBe('en')
        ->and($autre->fresh()->langue)->toBeNull();
});

test('le retour vers un autre site est refusé', function () {
    $this->from('https://site-malveillant.example/piege')
        ->post(route('langue', 'en'))
        ->assertRedirect(route('home'));
});

test('la fiche d\'un service traduit s\'affiche en anglais, celle d\'un service non traduit en français avec la mention', function () {
    $habitant = User::factory()->citoyen()->create(['langue' => 'en']);
    $traduit = Service::factory()->create(['nom' => 'État civil', 'description' => 'Actes de naissance.']);
    $nonTraduit = Service::factory()->create(['nom' => 'Sports et associations', 'description' => 'Réservation des gymnases.']);
    ServiceTranslationSeeder::remplir();

    $this->actingAs($habitant)->get(route('services.show', $traduit))
        ->assertOk()
        ->assertSee('<html lang="en"', false)
        ->assertSee('Civil registry')
        ->assertDontSee('data-test="contenu-en-francais"', false);

    $this->actingAs($habitant)->get(route('services.show', $nonTraduit))
        ->assertOk()
        ->assertSee('Réservation des gymnases.')
        ->assertSee('This content is not yet available in English and is shown in French.');
});

test('le catalogue et le formulaire de démarche s\'affichent en anglais', function () {
    $habitant = User::factory()->citoyen()->create(['langue' => 'en']);
    Service::factory()->create(['nom' => 'État civil']);
    ServiceTranslationSeeder::remplir();

    $this->actingAs($habitant)->get(route('services.index'))->assertOk()->assertSee('Civil registry');
    $this->actingAs($habitant)->get(route('demarches.create'))->assertOk()->assertSee('Documents to prepare');
});

test('l\'agent rattaché au service enregistre la traduction anglaise', function () {
    $service = Service::factory()->create();
    $agent = User::factory()->agent()->create();
    $agent->services()->attach($service);

    Livewire::actingAs($agent)
        ->test('pages::services.form', ['service' => $service])
        ->set('traductions.en.nom', 'Town library')
        ->call('save')
        ->assertHasNoErrors();

    expect($service->translations()->where('locale', 'en')->value('nom'))->toBe('Town library');
});

test('un citoyen ne peut pas ouvrir le formulaire de traduction d\'un service', function () {
    $service = Service::factory()->create();

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('services.edit', $service))
        ->assertForbidden();
});
