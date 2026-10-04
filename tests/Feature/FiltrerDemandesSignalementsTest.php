<?php

use App\Models\Demarche;
use App\Models\Service;
use App\Models\Signalement;
use App\Models\User;
use Livewire\Livewire;

/*
| F79 : filtrer et trier les démarches (« demandes ») et les signalements par sujet.
*/

test('un invité est redirigé vers la connexion, même avec des filtres', function () {
    $this->get(route('signalements.index', ['categorie' => 'voirie']))->assertRedirect(route('login'));
    $this->get(route('demarches.index', ['categorie' => 'sante']))->assertRedirect(route('login'));
});

test('le filtre par catégorie ne garde que les signalements de cette catégorie', function () {
    $user = User::factory()->citoyen()->create();
    Signalement::factory()->for($user)->create(['categorie' => 'voirie', 'lieu' => 'Nid-de-poule rue A']);
    Signalement::factory()->for($user)->create(['categorie' => 'eclairage', 'lieu' => 'Lampadaire éteint rue B']);

    Livewire::withQueryParams(['categorie' => 'voirie'])
        ->actingAs($user)
        ->test('pages::signalements.index')
        ->assertSee('Nid-de-poule rue A')
        ->assertDontSee('Lampadaire éteint rue B');
});

test('le filtre par sujet ne garde que les démarches des services de ce sujet', function () {
    $user = User::factory()->citoyen()->create();
    $sante = Service::factory()->create(['categorie' => 'sante']);
    $culture = Service::factory()->create(['categorie' => 'culture']);
    Demarche::factory()->for($user)->for($sante)->create(['titre' => 'Demande carnet de vaccination']);
    Demarche::factory()->for($user)->for($culture)->create(['titre' => 'Inscription médiathèque']);

    Livewire::withQueryParams(['categorie' => 'sante'])
        ->actingAs($user)
        ->test('pages::demarches.index')
        ->assertSee('Demande carnet de vaccination')
        ->assertDontSee('Inscription médiathèque');
});

test('catégorie et tri combinés : bons signalements dans le bon ordre', function () {
    $user = User::factory()->citoyen()->create();
    Signalement::factory()->for($user)->create(['categorie' => 'voirie', 'lieu' => 'Voirie ancienne', 'created_at' => now()->subDays(3)]);
    Signalement::factory()->for($user)->create(['categorie' => 'voirie', 'lieu' => 'Voirie récente', 'created_at' => now()->subDay()]);
    Signalement::factory()->for($user)->create(['categorie' => 'eau', 'lieu' => 'Fuite d\'eau', 'created_at' => now()->subDays(2)]);

    $composant = Livewire::withQueryParams(['categorie' => 'voirie', 'tri' => 'anciens'])
        ->actingAs($user)
        ->test('pages::signalements.index')
        ->assertSeeInOrder(['Voirie ancienne', 'Voirie récente'])
        ->assertDontSee('Fuite d\'eau');

    // Chaque élément est rendu deux fois (liste mobile + tableau) : l'ordre est vérifié sur les résultats.
    expect($composant->instance()->items->pluck('lieu')->all())->toBe(['Voirie ancienne', 'Voirie récente']);
});

test('tri des démarches : plus récentes, plus anciennes et par statut', function () {
    $user = User::factory()->citoyen()->create();
    Demarche::factory()->for($user)->create(['titre' => 'Démarche A traitée', 'statut' => 'traitee', 'created_at' => now()->subDays(3)]);
    Demarche::factory()->for($user)->create(['titre' => 'Démarche B déposée', 'statut' => 'deposee', 'created_at' => now()->subDays(2)]);
    Demarche::factory()->for($user)->create(['titre' => 'Démarche C en cours', 'statut' => 'en_cours', 'created_at' => now()->subDay()]);

    $ordre = fn (string $tri) => Livewire::withQueryParams(['tri' => $tri])
        ->actingAs($user)
        ->test('pages::demarches.index')
        ->instance()->items->pluck('titre')->all();

    expect($ordre('recents'))->toBe(['Démarche C en cours', 'Démarche B déposée', 'Démarche A traitée'])
        ->and($ordre('anciens'))->toBe(['Démarche A traitée', 'Démarche B déposée', 'Démarche C en cours'])
        ->and($ordre('statut'))->toBe(['Démarche B déposée', 'Démarche C en cours', 'Démarche A traitée']);
});

test('tri des signalements par état', function () {
    $user = User::factory()->citoyen()->create();
    Signalement::factory()->for($user)->create(['statut' => 'resolu', 'lieu' => 'Signalement résolu']);
    Signalement::factory()->for($user)->create(['statut' => 'nouveau', 'lieu' => 'Signalement nouveau']);

    $lieux = Livewire::withQueryParams(['tri' => 'statut'])
        ->actingAs($user)
        ->test('pages::signalements.index')
        ->instance()->items->pluck('lieu')->all();

    expect($lieux)->toBe(['Signalement nouveau', 'Signalement résolu']);
});

test('le lien de la page 2 conserve les filtres', function () {
    $user = User::factory()->citoyen()->create();
    Signalement::factory()->for($user)->count(12)->create(['categorie' => 'voirie']);

    $suivante = Livewire::withQueryParams(['categorie' => 'voirie', 'tri' => 'anciens'])
        ->actingAs($user)
        ->test('pages::signalements.index')
        ->instance()->items->nextPageUrl();

    expect($suivante)->toContain('categorie=voirie')->toContain('tri=anciens')->toContain('page=2');
});

test('des valeurs invalides sont ignorées sans erreur', function () {
    $user = User::factory()->citoyen()->create();
    Signalement::factory()->for($user)->create(['lieu' => 'Mon signalement visible']);
    Demarche::factory()->for($user)->create(['titre' => 'Ma démarche visible']);

    Livewire::withQueryParams(['tri' => 'password', 'categorie' => 'inconnue', 'statut' => 'x'])
        ->actingAs($user)
        ->test('pages::signalements.index')
        ->assertOk()
        ->assertSet('tri', 'recents')
        ->assertSet('filterCategorie', '')
        ->assertSee('Mon signalement visible');

    Livewire::withQueryParams(['tri' => 'password', 'categorie' => 'inconnue', 'service' => '1 OR 1=1'])
        ->actingAs($user)
        ->test('pages::demarches.index')
        ->assertOk()
        ->assertSet('tri', 'recents')
        ->assertSet('filterServiceId', '')
        ->assertSee('Ma démarche visible');
});

test('aucun résultat : message clair et bouton Réinitialiser', function () {
    $user = User::factory()->citoyen()->create();
    Signalement::factory()->for($user)->create(['categorie' => 'voirie', 'lieu' => 'Trou dans la chaussée']);

    Livewire::withQueryParams(['categorie' => 'eau'])
        ->actingAs($user)
        ->test('pages::signalements.index')
        ->assertSee('Aucun signalement ne correspond à ces filtres.')
        ->assertSee('Réinitialiser')
        ->call('reinitialiser')
        ->assertSet('filterCategorie', '')
        ->assertSee('Trou dans la chaussée');

    Livewire::withQueryParams(['categorie' => 'culture'])
        ->actingAs($user)
        ->test('pages::demarches.index')
        ->assertSee('Aucune demande ne correspond à ces filtres.')
        ->assertSee('Réinitialiser');
});

test('les filtres n\'élargissent jamais la visibilité', function () {
    $moi = User::factory()->citoyen()->create();
    $autre = User::factory()->citoyen()->create();
    $sante = Service::factory()->create(['categorie' => 'sante']);
    Signalement::factory()->for($autre)->create(['categorie' => 'voirie', 'statut' => 'resolu', 'lieu' => 'Signalement privé du voisin']);
    $demarcheVoisin = Demarche::factory()->for($autre)->for($sante)->create(['titre' => 'Démarche privée du voisin']);

    Livewire::withQueryParams(['categorie' => 'voirie', 'statut' => 'resolu', 'tri' => 'statut'])
        ->actingAs($moi)
        ->test('pages::signalements.index')
        ->assertDontSee('Signalement privé du voisin');

    Livewire::withQueryParams(['categorie' => 'sante', 'service' => (string) $sante->id, 'mine' => '0'])
        ->actingAs($moi)
        ->test('pages::demarches.index')
        ->assertDontSee('Démarche privée du voisin');

    $this->actingAs($moi)->get(route('demarches.show', $demarcheVoisin))->assertForbidden();
    $this->actingAs($moi)->get(route('signalements.show', Signalement::first()))->assertForbidden();
});
