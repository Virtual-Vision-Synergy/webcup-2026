<?php

use App\Models\CreneauRendezVous;
use App\Models\Demarche;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
| F64 — État actuel des services (Disponible / Perturbé / Indisponible). Horloge figée : lundi 5 octobre 2026.
*/

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-05 08:00:00', 'UTC'));
});

function serviceIndisponible(array $attributs = []): Service
{
    $service = Service::factory()->create(['nom' => 'Médiathèque Ravinala', 'duree_rendez_vous' => 30, ...$attributs]);
    $service->mettreAJourEtat(
        Service::ETAT_INDISPONIBLE,
        'Fermée pour travaux',
        Carbon::parse('2026-10-12'),
        'Point lecture de la mairie annexe',
        'https://example.com/point-lecture',
    );

    return $service;
}

test('la fiche affiche le libellé de chaque état', function (string $etat, string $libelle) {
    $service = Service::factory()->create();
    $service->mettreAJourEtat($etat, $etat === Service::ETAT_DISPONIBLE ? null : 'Motif de test');

    $this->actingAs(User::factory()->create())
        ->get(route('services.show', $service))
        ->assertOk()
        ->assertSee('data-service-etat="'.$etat.'"', false)
        ->assertSee($libelle);
})->with([
    'disponible' => [Service::ETAT_DISPONIBLE, 'Disponible'],
    'perturbé' => [Service::ETAT_PERTURBE, 'Perturbé'],
    'indisponible' => [Service::ETAT_INDISPONIBLE, 'Indisponible'],
]);

test('service indisponible : motif, retour prévu et alternative avant le bouton de démarche désactivé', function () {
    $service = serviceIndisponible();

    $this->actingAs(User::factory()->create())
        ->get(route('services.show', $service))
        ->assertSeeInOrder([
            'Indisponible',
            'Fermée pour travaux',
            'Retour prévu le lundi 12 octobre 2026',
            'Point lecture de la mairie annexe',
            'https://example.com/point-lecture',
            'Démarche momentanément impossible',
        ])
        ->assertDontSee('Commencer une démarche')
        ->assertDontSee('Prendre rendez-vous');
});

test('service perturbé : encart avant le bouton de démarche, qui reste actif', function () {
    $service = Service::factory()->create(['duree_rendez_vous' => 30]);
    $service->mettreAJourEtat(Service::ETAT_PERTURBE, 'Forte affluence');

    $this->actingAs(User::factory()->create())
        ->get(route('services.show', $service))
        ->assertSeeInOrder(['Perturbé', 'Forte affluence', 'Date de retour non connue', 'Commencer une démarche', 'Prendre rendez-vous'])
        ->assertDontSee('Démarche momentanément impossible');
});

test('date de retour dépassée : mention de mise à jour en cours, état inchangé', function () {
    $service = serviceIndisponible();
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));

    $this->actingAs(User::factory()->create())
        ->get(route('services.show', $service))
        ->assertSee('Retour prévu dépassé, informations en cours de mise à jour.')
        ->assertSee('Démarche momentanément impossible');
});

test('le catalogue affiche le même état pour chaque service et filtre les disponibles', function () {
    Service::factory()->create(['nom' => 'Urbanisme']);
    serviceIndisponible();
    Service::factory()->create(['nom' => 'État civil'])->mettreAJourEtat(Service::ETAT_PERTURBE, 'Forte affluence');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('services.index'))
        ->assertSee('data-service-etat="disponible"', false)
        ->assertSee('data-service-etat="perturbe"', false)
        ->assertSee('data-service-etat="indisponible"', false)
        ->assertSee(['Disponible', 'Perturbé', 'Indisponible']);

    Livewire::actingAs($user)
        ->test('pages::services.index')
        ->set('disponiblesSeulement', true)
        ->assertSee('Urbanisme')
        ->assertDontSee('Médiathèque Ravinala')
        ->assertDontSee('État civil');
});

test('lien direct de démarche vers un service indisponible : retour sur la fiche avec le message', function () {
    $service = serviceIndisponible();

    $this->actingAs(User::factory()->create())
        ->get(route('demarches.create', ['service' => $service->id]))
        ->assertRedirect(route('services.show', $service));

    expect(session('service-indisponible'))
        ->toBe('Ce service est actuellement indisponible : Fermée pour travaux. Retour prévu le lundi 12 octobre 2026. Vous pouvez : Point lecture de la mairie annexe.');

    $this->get(route('services.show', $service))->assertSee('Ce service est actuellement indisponible : Fermée pour travaux.');
});

test('envoi d’une démarche sur un service indisponible : refusé, aucune démarche créée', function () {
    $service = serviceIndisponible();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::demarches.form')
        ->set('titre', 'Inscription')
        ->set('description', 'Je voudrais m’inscrire.')
        ->set('service_id', (string) $service->id)
        ->call('save')
        ->assertRedirect(route('services.show', $service));

    expect(Demarche::count())->toBe(0);
});

test('service perturbé : la démarche est possible', function () {
    $service = Service::factory()->create();
    $service->mettreAJourEtat(Service::ETAT_PERTURBE, 'Forte affluence');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::demarches.form')
        ->set('titre', 'Acte de naissance')
        ->set('description', 'Copie intégrale.')
        ->set('service_id', (string) $service->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Demarche::whereBelongsTo($user)->where('service_id', $service->id)->count())->toBe(1);
});

test('une démarche en cours reste modifiable quand son service devient indisponible', function () {
    $service = Service::factory()->create();
    $demarche = Demarche::factory()->create(['service_id' => $service->id]);
    $service->mettreAJourEtat(Service::ETAT_INDISPONIBLE, 'Panne');

    Livewire::actingAs($demarche->user)
        ->test('pages::demarches.form', ['demarche' => $demarche])
        ->set('titre', 'Titre corrigé')
        ->call('save')
        ->assertHasNoErrors();

    expect($demarche->fresh()->titre)->toBe('Titre corrigé');
});

test('prise de rendez-vous sur un service indisponible : refusée avec retour sur la fiche', function () {
    $service = serviceIndisponible();
    $creneau = CreneauRendezVous::factory()->a('2026-10-06 06:00:00', 30)->for($service)->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::rendez-vous.form', ['serviceSlug' => $service->slug, 'creneauId' => (string) $creneau->id])
        ->assertRedirect(route('services.show', $service));

    Livewire::actingAs(User::factory()->create())
        ->test('pages::rendez-vous.form')
        ->call('choisirService', $service->slug)
        ->assertRedirect(route('services.show', $service));

    expect(RendezVous::count())->toBe(0);
});

test('les champs d’état ne sont pas remplissables par le formulaire général', function () {
    $service = Service::factory()->create();

    $service->fill([
        'perturbe_depuis' => now(),
        'indisponible_depuis' => now(),
        'motif_indisponibilite' => 'Piratage',
        'alternative_texte' => 'Piratage',
        'alternative_url' => 'https://pirate.example',
        'etat_mis_a_jour_le' => now(),
    ])->save();

    expect($service->fresh()->etat())->toBe(Service::ETAT_DISPONIBLE)
        ->and($service->fresh()->motif_indisponibilite)->toBeNull()
        ->and($service->fresh()->alternative_url)->toBeNull();
});

test('un citoyen ne peut pas changer l’état d’un service (403)', function () {
    $service = Service::factory()->create();

    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::services.show', ['service' => $service])
        ->assertDontSee("Mettre à jour l'état")
        ->set('etat', Service::ETAT_INDISPONIBLE)
        ->set('motif', 'Piratage')
        ->call('mettreAJourEtat')
        ->assertForbidden();

    expect($service->fresh()->etat())->toBe(Service::ETAT_DISPONIBLE);
});

test('un agent d’un autre service ne peut pas changer l’état (403)', function () {
    $service = Service::factory()->create();
    $agent = User::factory()->agentDe(Service::factory()->create())->create();

    Livewire::actingAs($agent)
        ->test('pages::services.show', ['service' => $service])
        ->set('etat', Service::ETAT_INDISPONIBLE)
        ->set('motif', 'Panne')
        ->call('mettreAJourEtat')
        ->assertForbidden();

    expect($service->fresh()->etat())->toBe(Service::ETAT_DISPONIBLE);
});

test('l’agent du service et l’admin peuvent changer l’état', function (string $role) {
    $service = Service::factory()->create();
    $user = $role === 'admin' ? User::factory()->admin()->create() : User::factory()->agentDe($service)->create();

    Livewire::actingAs($user)
        ->test('pages::services.show', ['service' => $service])
        ->set('etat', Service::ETAT_INDISPONIBLE)
        ->set('motif', 'Fermée pour travaux')
        ->set('retourPrevuLe', '2026-10-12')
        ->set('alternativeTexte', 'Point lecture')
        ->set('alternativeUrl', 'https://example.com')
        ->call('mettreAJourEtat')
        ->assertHasNoErrors();

    $service->refresh();
    expect($service->etat())->toBe(Service::ETAT_INDISPONIBLE)
        ->and($service->motif_indisponibilite)->toBe('Fermée pour travaux')
        ->and($service->retour_prevu_le->toDateString())->toBe('2026-10-12')
        ->and($service->etat_mis_a_jour_le)->not->toBeNull();

    Livewire::actingAs($user)
        ->test('pages::services.show', ['service' => $service])
        ->set('etat', Service::ETAT_DISPONIBLE)
        ->call('mettreAJourEtat')
        ->assertHasNoErrors();

    $service->refresh();
    expect($service->etat())->toBe(Service::ETAT_DISPONIBLE)
        ->and($service->motif_indisponibilite)->toBeNull()
        ->and($service->alternative_texte)->toBeNull();
})->with(['agent du service', 'admin']);

test('mise à jour de l’état : motif obligatoire et lien http(s) uniquement', function () {
    $service = Service::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::services.show', ['service' => $service])
        ->set('etat', Service::ETAT_PERTURBE)
        ->set('motif', '')
        ->set('alternativeUrl', 'javascript:alert(1)')
        ->call('mettreAJourEtat')
        ->assertHasErrors(['motif' => 'required', 'alternativeUrl']);

    expect($service->fresh()->etat())->toBe(Service::ETAT_DISPONIBLE);
});
