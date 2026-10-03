<?php

use App\Models\Demarche;
use App\Models\Signalement;
use App\Models\User;
use Livewire\Livewire;

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('agent.tableau-de-bord'))->assertRedirect(route('login'));
});

test('un citoyen reçoit un 403 sur le tableau de bord', function () {
    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('agent.tableau-de-bord'))
        ->assertForbidden();
});

test('un administrateur accède au tableau de bord', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('agent.tableau-de-bord'))
        ->assertOk()
        ->assertSee('Tableau de bord');
});

test('un agent voit les compteurs, l’activité et les dernières demandes', function () {
    $this->travelTo(now()->setTime(12, 0));

    $recente = Demarche::factory()->create(['titre' => 'Certificat de résidence urgent', 'statut' => 'deposee']);
    Demarche::factory()->create(['statut' => 'en_cours']);
    Demarche::factory()->create(['statut' => 'traitee', 'created_at' => now()->subDays(3)]);
    Demarche::factory()->create(['statut' => 'refusee', 'created_at' => now()->subDays(10)]);
    Signalement::factory()->nouveau()->create();
    Signalement::factory()->create(['statut' => 'resolu']);

    $page = Livewire::actingAs(User::factory()->agent()->create())->test('pages::agent.tableau-de-bord');

    expect($page->instance()->compteurs)->toBe([
        'total' => 4,
        'aujourdhui' => 2,
        'en_attente' => 1,
        'signalements' => 2,
        'signalements_nouveaux' => 1,
    ]);

    $activite = $page->instance()->activite;
    expect($activite)->toHaveCount(7)
        ->and(array_column($activite, 'total'))->toBe([0, 0, 0, 1, 0, 0, 2]);

    $page->assertSee('Certificat de résidence urgent')
        ->assertSee(route('demarches.show', $recente), false);
});

test('le tableau de bord affiche des états vides sans données', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.tableau-de-bord'))
        ->assertOk()
        ->assertSee(['Aucune demande cette semaine', 'Aucune demande pour l’instant', 'Aucun signalement']);
});

test('le tableau de bord est accessible en un clic depuis la navigation agent', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.demandes'))
        ->assertSee(route('agent.tableau-de-bord'), false);
});
