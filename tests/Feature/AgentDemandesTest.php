<?php

use App\Models\Demarche;
use App\Models\User;
use Livewire\Livewire;

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('agent.demandes'))->assertRedirect(route('login'));
});

test('un citoyen reçoit un 403 sur la liste des demandes', function () {
    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('agent.demandes'))
        ->assertForbidden();
});

test('un agent voit toutes les demandes des habitants avec leur état', function () {
    Demarche::factory()->create(['titre' => 'Lampadaire en panne', 'statut' => 'deposee']);
    Demarche::factory()->create(['titre' => 'Nid de poule réparé', 'statut' => 'traitee']);

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.demandes'))
        ->assertOk()
        ->assertSee(['Lampadaire en panne', 'Nid de poule réparé', 'Action attendue']);
});

test('le filtre « en attente d’action » masque les demandes clôturées', function () {
    Demarche::factory()->create(['titre' => 'Lampadaire en panne', 'statut' => 'en_cours']);
    Demarche::factory()->create(['titre' => 'Nid de poule réparé', 'statut' => 'refusee']);

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::agent.demandes')
        ->set('enAttente', true)
        ->assertSee('Lampadaire en panne')
        ->assertDontSee('Nid de poule réparé');
});

test('un agent peut changer l’état d’une demande', function () {
    $demarche = Demarche::factory()->create(['statut' => 'deposee']);

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::agent.demandes')
        ->call('changerStatut', $demarche->id, 'traitee')
        ->assertHasNoErrors();

    expect($demarche->fresh()->statut)->toBe('traitee');
});

test('un statut inconnu est refusé', function () {
    $demarche = Demarche::factory()->create(['statut' => 'deposee']);

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::agent.demandes')
        ->call('changerStatut', $demarche->id, 'pirate')
        ->assertStatus(422);

    expect($demarche->fresh()->statut)->toBe('deposee');
});

test('un citoyen ne peut pas appeler le changement d’état', function () {
    $citoyen = User::factory()->citoyen()->create();
    $demarche = Demarche::factory()->for($citoyen)->create(['statut' => 'deposee']);

    Livewire::actingAs($citoyen)
        ->test('pages::agent.demandes')
        ->assertForbidden();

    expect($demarche->fresh()->statut)->toBe('deposee');
});
