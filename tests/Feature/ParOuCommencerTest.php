<?php

use App\Models\Demarche;
use App\Models\Onboarding;
use App\Models\Service;
use App\Models\User;
use App\Services\ParOuCommencer;
use Livewire\Livewire;

/**
 * Catalogue minimal : un service par catégorie utile, plus un service indisponible.
 *
 * @return array<string, Service>
 */
function catalogueParOuCommencer(): array
{
    $services = [];
    foreach ([
        'administratif' => 'État civil',
        'urbanisme' => 'Urbanisme',
        'education' => 'Petite enfance et écoles',
        'social' => 'Action sociale (CCAS)',
        'economie' => 'Marchés et commerce',
        'sante' => 'Santé publique',
        'culture' => 'Médiathèque Ravinala',
    ] as $categorie => $nom) {
        $services[$categorie] = Service::factory()->create(['nom' => $nom, 'categorie' => $categorie]);
    }

    $services['ferme'] = Service::factory()->create(['nom' => 'Crèche fermée', 'categorie' => 'education']);
    $services['ferme']->forceFill(['indisponible_depuis' => now(), 'motif_indisponibilite' => 'Travaux'])->save();

    return $services;
}

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('onboarding.par-ou-commencer'))->assertRedirect(route('login'));
});

test('un agent ou un admin reçoit 403 et ne peut rien enregistrer', function (string $role) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('onboarding.par-ou-commencer'))->assertForbidden();
    Livewire::actingAs($user)->test('pages::onboarding.par-ou-commencer')->assertForbidden();

    expect(Onboarding::count())->toBe(0);
})->with(['agent', 'admin']);

test('un habitant répond aux questions et reçoit 3 à 5 services menant à leur démarche', function () {
    $services = catalogueParOuCommencer();
    $citoyen = User::factory()->citoyen()->create();

    Livewire::actingAs($citoyen)
        ->test('pages::onboarding.par-ou-commencer')
        ->assertSee('J’ai des enfants')
        ->set('situations', ['famille'])
        ->call('enregistrer')
        ->assertHasNoErrors()
        ->assertSee(['Petite enfance et écoles', 'Pour : J’ai des enfants', 'Modifier mes réponses'])
        ->assertSee(route('demarches.create', ['service' => $services['education']->id]), false)
        ->assertDontSee('Crèche fermée');

    $recommandations = ParOuCommencer::pour($citoyen->fresh())->recommandations();
    expect($recommandations->count())->toBeGreaterThanOrEqual(ParOuCommencer::MINIMUM)->toBeLessThanOrEqual(ParOuCommencer::MAXIMUM)
        ->and($recommandations->first()->is($services['education']))->toBeTrue()
        ->and($recommandations->pluck('id'))->not->toContain($services['ferme']->id);
});

test('les réponses sont enregistrées et modifiables sans refaire le parcours', function () {
    $services = catalogueParOuCommencer();
    $citoyen = User::factory()->citoyen()->create();
    Onboarding::factory()->termine()->for($citoyen)->create();

    Livewire::actingAs($citoyen)->test('pages::onboarding.par-ou-commencer')
        ->set('situations', ['famille'])->call('enregistrer');

    Livewire::actingAs($citoyen->fresh())->test('pages::onboarding.par-ou-commencer')
        ->assertSet('modification', false)
        ->assertSet('situations', ['famille'])
        ->call('modifier')
        ->set('situations', ['sante'])
        ->call('enregistrer')
        ->assertSee('Santé publique');

    $onboarding = $citoyen->fresh()->onboarding;
    expect($onboarding->situation)->toBe(['sante'])
        ->and($onboarding->completed_at)->not->toBeNull();
});

test('une situation inconnue est refusée', function () {
    $citoyen = User::factory()->citoyen()->create();

    Livewire::actingAs($citoyen)->test('pages::onboarding.par-ou-commencer')
        ->set('situations', ['logement', '<script>alert(1)</script>'])
        ->call('enregistrer')
        ->assertHasErrors('situations.1');

    expect(Onboarding::whereNotNull('situation')->count())->toBe(0);
});

test('la situation d\'un habitant n\'est jamais visible par un autre', function () {
    catalogueParOuCommencer();
    $premier = User::factory()->citoyen()->create();
    $second = User::factory()->citoyen()->create();

    Livewire::actingAs($premier)->test('pages::onboarding.par-ou-commencer')
        ->set('situations', ['famille', 'sante'])->call('enregistrer');

    Livewire::actingAs($second)->test('pages::onboarding.par-ou-commencer')
        ->assertSet('situations', [])
        ->assertSet('modification', true);

    expect($second->fresh()->onboarding)->toBeNull();
});

test('le bloc du tableau de bord est ouvert tant qu\'aucune démarche n\'est commencée, puis se replie', function () {
    catalogueParOuCommencer();
    $citoyen = User::factory()->citoyen()->create();
    Onboarding::factory()->termine()->for($citoyen)->create();

    $this->actingAs($citoyen)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(['Par où commencer ?', 'Trouver mes services utiles'])
        ->assertSee('data-ouvert="oui"', false);

    Demarche::factory()->for($citoyen)->create();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Revoir les services recommandés pour votre situation.')
        ->assertSee('data-ouvert="non"', false);
});

test('un agent ne voit pas le bloc sur son tableau de bord', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Trouver mes services utiles');
});
