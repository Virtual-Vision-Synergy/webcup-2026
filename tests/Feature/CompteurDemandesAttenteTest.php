<?php

use App\Models\Demarche;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    // Le tableau de bord agent appelle aussi l'API Nova Terra : aucun appel réseau pendant les tests.
    Http::fake();
    config()->set('services.novaterra.key', null);
});

test('un agent voit le nombre de demandes en attente de prise en charge', function () {
    Demarche::factory()->count(3)->create(['statut' => 'deposee']);
    Demarche::factory()->create(['statut' => 'en_cours']);
    Demarche::factory()->create(['statut' => 'traitee']);

    $this->actingAs(agentDeTousLesServices())
        ->get(route('agent.index'))
        ->assertOk()
        ->assertSee('data-test="compteur-demandes-attente"', false)
        ->assertSeeInOrder(['3', 'demandes en attente de prise en charge'])
        ->assertSee('Voir ces demandes');
});

test('le compteur suit les changements d’état et les nouvelles demandes', function () {
    $demarches = Demarche::factory()->count(2)->create(['statut' => 'deposee']);

    $compteur = Livewire::actingAs(agentDeTousLesServices())
        ->test('compteur-demandes-attente')
        ->assertSeeInOrder(['2', 'demandes en attente']);

    $demarches->first()->changerStatut('en_cours');
    $compteur->call('$refresh')->assertSeeInOrder(['1', 'demande en attente de prise en charge']);

    Demarche::factory()->for($demarches->first()->service)->create(['statut' => 'deposee']);
    $compteur->call('$refresh')->assertSeeInOrder(['2', 'demandes en attente']);
});

test('sans demande en attente, le compteur affiche « Aucune demande en attente »', function () {
    Demarche::factory()->create(['statut' => 'traitee']);

    Livewire::actingAs(agentDeTousLesServices())
        ->test('compteur-demandes-attente')
        ->assertSee('Aucune demande en attente')
        ->assertDontSee('Voir ces demandes');
});

test('un administrateur voit le compteur', function () {
    Demarche::factory()->create(['statut' => 'deposee']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('agent.index'))
        ->assertOk()
        ->assertSee('demande en attente de prise en charge');
});

test('un citoyen reçoit un 403 sur le tableau de bord et sur le compteur', function () {
    $citoyen = User::factory()->citoyen()->create();

    $this->actingAs($citoyen)->get(route('agent.index'))->assertForbidden();

    Livewire::actingAs($citoyen)->test('compteur-demandes-attente')->assertForbidden();
});

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('agent.index'))->assertRedirect(route('login'));
});

test('le statut d’une demande ne peut pas être imposé par assignation de masse', function () {
    $demarche = Demarche::make([
        'titre' => 'Lampadaire en panne',
        'service_id' => Service::factory()->create()->id,
        'statut' => 'traitee',
    ]);

    expect($demarche->statut)->toBe('deposee');
});
