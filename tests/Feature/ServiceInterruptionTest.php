<?php

use App\Models\CreneauRendezVous;
use App\Models\Demarche;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\ServiceInterruption;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
| F38 — Indisponibilité d'un service municipal. Horloge figée : samedi 3 octobre 2026, 08:00 UTC (11 h à Nova Terra).
*/

beforeEach(function () {
    $this->travelTo(now()->parse('2026-10-03 08:00:00', 'UTC'));

    $this->service = Service::factory()->create(['nom' => 'État civil', 'duree_rendez_vous' => 30]);
});

function interrompre(Service $service, array $etat = []): ServiceInterruption
{
    return ServiceInterruption::factory()->for($service)->create($etat);
}

function formulaireDisponibilite(User $user, Service $service): Testable
{
    return Livewire::actingAs($user)->test('pages::agent.services.disponibilite', ['service' => $service]);
}

// --- Accès et droits -------------------------------------------------------------------------

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('agent.services.availability', $this->service))->assertRedirect(route('login'));
    $this->get(route('agent.services.index'))->assertRedirect(route('login'));
});

test('un citoyen reçoit un 403 sur la liste et le formulaire de disponibilité', function () {
    $citoyen = User::factory()->citoyen()->create();

    $this->actingAs($citoyen)->get(route('agent.services.index'))->assertForbidden();
    $this->actingAs($citoyen)->get(route('agent.services.availability', $this->service))->assertForbidden();
});

test('un citoyen reçoit un 403 sur les actions save et retablir', function () {
    $agent = User::factory()->agent()->create();
    $citoyen = User::factory()->citoyen()->create();
    interrompre($this->service);

    $page = formulaireDisponibilite($agent, $this->service);

    Livewire::actingAs($citoyen)->test('pages::agent.services.disponibilite', ['service' => $this->service])->assertForbidden();

    // Appels directs des actions par un citoyen sur une page ouverte par un agent (requête forgée).
    $this->actingAs($citoyen);
    $page->call('save')->assertForbidden();
    $page->call('retablir')->assertForbidden();

    expect($this->service->interruptionCourante()->first())->not->toBeNull();
});

test('un agent voit la liste des services avec leur statut', function () {
    interrompre($this->service, ['type' => 'incident']);
    Service::factory()->create(['nom' => 'Urbanisme']);

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.services.index'))
        ->assertOk()
        ->assertSee('Indisponible · Incident')
        ->assertSee('Disponible');
});

// --- Marquer indisponible --------------------------------------------------------------------

test('un agent marque un service indisponible', function () {
    $agent = User::factory()->agent()->create();
    $alternatif = Service::factory()->create(['nom' => 'Mairie annexe du quartier Nord']);

    formulaireDisponibilite($agent, $this->service)
        ->set('type', 'incident')
        ->set('motif', 'Panne du logiciel de délivrance des actes')
        ->set('retour_prevu_at', '2026-10-05T14:00')
        ->set('alternative', 'Mairie annexe du quartier Nord, 8 h – 12 h')
        ->set('alternative_service_id', (string) $alternatif->id)
        ->call('save')
        ->assertHasNoErrors();

    $interruption = ServiceInterruption::sole();

    expect($interruption->service_id)->toBe($this->service->id)
        ->and($interruption->created_by)->toBe($agent->id)
        ->and($interruption->type)->toBe('incident')
        ->and($interruption->alternative_service_id)->toBe($alternatif->id)
        ->and($interruption->debut_at->equalTo(now()))->toBeTrue()
        // 14 h à Nova Terra (UTC+3) = 11 h UTC.
        ->and($interruption->retour_prevu_at->format('Y-m-d H:i'))->toBe('2026-10-05 11:00')
        ->and($this->service->fresh()->estIndisponible())->toBeTrue();
});

test('un admin peut aussi marquer un service indisponible', function () {
    formulaireDisponibilite(User::factory()->admin()->create(), $this->service)
        ->set('motif', 'Maintenance du serveur')
        ->set('alternative', 'Revenez lundi.')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->service->fresh()->estIndisponible())->toBeTrue();
});

test('service_id, created_by et retabli_at envoyés en masse sont ignorés', function () {
    $autre = Service::factory()->create();
    $intrus = User::factory()->create();

    $interruption = new ServiceInterruption([
        'type' => 'maintenance',
        'motif' => 'Test',
        'alternative' => 'Test',
        'service_id' => $autre->id,
        'created_by' => $intrus->id,
        'retabli_at' => now(),
        'retabli_par' => $intrus->id,
        'debut_at' => now()->addYear(),
    ]);

    expect($interruption->service_id)->toBeNull()
        ->and($interruption->created_by)->toBeNull()
        ->and($interruption->retabli_at)->toBeNull()
        ->and($interruption->retabli_par)->toBeNull()
        ->and($interruption->debut_at)->toBeNull();
});

test('le service vient de la route et ne peut pas être changé par le navigateur', function () {
    formulaireDisponibilite(User::factory()->agent()->create(), $this->service)
        ->set('service', Service::factory()->create());
})->throws(Exception::class, 'locked property');

test('la validation refuse un formulaire incomplet ou incohérent', function (array $saisie, string $champ) {
    formulaireDisponibilite(User::factory()->agent()->create(), $this->service)
        ->set(array_merge(['motif' => 'Panne', 'alternative' => 'Mairie annexe'], $saisie))
        ->call('save')
        ->assertHasErrors($champ);

    expect(ServiceInterruption::count())->toBe(0);
})->with([
    'motif manquant' => [['motif' => ''], 'motif'],
    'alternative manquante' => [['alternative' => ''], 'alternative'],
    'type inconnu' => [['type' => 'demolition'], 'type'],
    'date de retour passée' => [['retour_prevu_at' => '2026-10-01T09:00'], 'retour_prevu_at'],
]);

test('les messages de validation sont en français', function () {
    formulaireDisponibilite(User::factory()->agent()->create(), $this->service)
        ->set('motif', '')
        ->set('alternative', 'Mairie annexe')
        ->set('retour_prevu_at', '2026-10-01T09:00')
        ->call('save')
        ->assertSee('Le champ motif est obligatoire.')
        ->assertSee('La date de retour prévue doit être dans le futur.');
});

test('le service alternatif doit être différent du service interrompu', function () {
    formulaireDisponibilite(User::factory()->agent()->create(), $this->service)
        ->set('motif', 'Panne')
        ->set('alternative', 'Mairie annexe')
        ->set('alternative_service_id', (string) $this->service->id)
        ->call('save')
        ->assertHasErrors('alternative_service_id');
});

test('une deuxième déclaration met à jour l’interruption en cours au lieu d’en créer une autre', function () {
    $agent = User::factory()->agent()->create();
    interrompre($this->service, ['motif' => 'Ancien motif']);

    formulaireDisponibilite($agent, $this->service)
        ->assertSet('motif', 'Ancien motif')
        ->set('motif', 'Nouveau motif')
        ->call('save')
        ->assertHasNoErrors();

    expect(ServiceInterruption::count())->toBe(1)
        ->and(ServiceInterruption::sole()->motif)->toBe('Nouveau motif');
});

// --- Affichage côté habitant -----------------------------------------------------------------

test('le catalogue affiche le statut et la date de retour en français', function () {
    interrompre($this->service, ['type' => 'incident', 'retour_prevu_at' => now()->parse('2026-10-03 11:00:00', 'UTC')]);

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('services.index'))
        ->assertOk()
        ->assertSee('État civil')
        ->assertSee('Indisponible · Incident')
        ->assertSee('Retour prévu : samedi 3 octobre à 14 h');
});

test('la fiche affiche le motif, le retour prévu et l’alternative cliquable, sans le nom de l’agent', function () {
    $agent = User::factory()->agent()->create(['name' => 'Agent Rakotobe']);
    $annexe = Service::factory()->create(['nom' => 'Mairie annexe du quartier Nord']);
    interrompre($this->service, [
        'type' => 'incident',
        'motif' => 'Panne du logiciel de délivrance des actes',
        'retour_prevu_at' => now()->parse('2026-10-05 06:30:00', 'UTC'),
        'alternative' => 'Rendez-vous possible à la mairie annexe.',
        'alternative_service_id' => $annexe->id,
        'created_by' => $agent->id,
    ]);

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('services.show', $this->service))
        ->assertOk()
        ->assertSee('role="status"', false)
        ->assertSee('Panne du logiciel de délivrance des actes')
        ->assertSee('Retour prévu : lundi 5 octobre à 9 h 30')
        ->assertSee('Que faire en attendant ?')
        ->assertSee('Rendez-vous possible à la mairie annexe.')
        ->assertSee(route('services.show', $annexe))
        ->assertSee('Démarche suspendue pendant l’interruption')
        ->assertDontSee('Agent Rakotobe');
});

test('sans date connue, la fiche l’indique ; une date dépassée ne rend pas le service disponible', function () {
    $interruption = interrompre($this->service, ['retour_prevu_at' => null]);
    $citoyen = User::factory()->citoyen()->create();

    $this->actingAs($citoyen)->get(route('services.show', $this->service))->assertSee('Date de retour pas encore connue');

    $interruption->forceFill(['retour_prevu_at' => now()->subHour()])->save();

    $this->actingAs($citoyen)->get(route('services.show', $this->service))
        ->assertSee('mise à jour en cours par la mairie')
        ->assertSee('Démarche suspendue pendant l’interruption');
});

test('un motif contenant du HTML est affiché échappé', function () {
    interrompre($this->service, ['motif' => '<script>alert(1)</script>']);

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('services.show', $this->service))
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

test('un service rétabli n’affiche plus rien', function () {
    ServiceInterruption::factory()->retablie()->for($this->service)->create(['motif' => 'Ancienne panne']);

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('services.show', $this->service))
        ->assertDontSee('Ancienne panne')
        ->assertDontSee('Démarche suspendue pendant l’interruption')
        ->assertSee('Prendre rendez-vous');
});

// --- Blocage des démarches -------------------------------------------------------------------

test('l’ouverture d’une prise de rendez-vous sur un service indisponible renvoie vers la fiche', function () {
    interrompre($this->service);

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('appointments.create', ['service' => $this->service->slug]))
        ->assertRedirect(route('services.show', $this->service));
});

test('la confirmation directe d’un rendez-vous sur un service indisponible est refusée', function () {
    $citoyen = User::factory()->citoyen()->create();
    $creneau = CreneauRendezVous::factory()->a('2026-10-06 06:30:00', 30)->for($this->service)->create();

    $page = Livewire::actingAs($citoyen)
        ->test('pages::rendez-vous.form', ['serviceSlug' => $this->service->slug])
        ->call('choisirCreneau', $creneau->id);

    interrompre($this->service);

    $page->call('confirmer')->assertHasErrors('service');
    Livewire::actingAs($citoyen)->test('pages::rendez-vous.form')->call('choisirService', $this->service->slug)->assertHasErrors('service');

    expect(RendezVous::count())->toBe(0);
});

test('l’ouverture d’une démarche sur un service indisponible renvoie vers la fiche', function () {
    interrompre($this->service);

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('demarches.create', ['service' => $this->service->id]))
        ->assertRedirect(route('services.show', $this->service));
});

test('l’envoi direct d’une démarche sur un service indisponible est refusé', function () {
    interrompre($this->service);

    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::demarches.form')
        ->set('titre', 'Copie d’acte de naissance')
        ->set('description', 'Pour mon fils.')
        ->set('service_id', (string) $this->service->id)
        ->call('save')
        ->assertHasErrors('service_id');

    expect(Demarche::count())->toBe(0);
});

test('après « Rétablir », les démarches sont de nouveau possibles', function () {
    $agent = User::factory()->agent()->create();
    $citoyen = User::factory()->citoyen()->create();
    interrompre($this->service);

    formulaireDisponibilite($agent, $this->service)->call('retablir')->assertHasNoErrors();

    $interruption = ServiceInterruption::sole();
    expect($interruption->retabli_at)->not->toBeNull()
        ->and($interruption->retabli_par)->toBe($agent->id)
        ->and($this->service->fresh()->estIndisponible())->toBeFalse();

    $this->actingAs($citoyen)->get(route('appointments.create', ['service' => $this->service->slug]))->assertOk();

    Livewire::actingAs($citoyen)
        ->test('pages::demarches.form')
        ->set('titre', 'Copie d’acte de naissance')
        ->set('description', 'Pour mon fils.')
        ->set('service_id', (string) $this->service->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Demarche::count())->toBe(1);
});

test('les rendez-vous déjà pris ne sont ni supprimés ni modifiés par une interruption', function () {
    $rendezVous = RendezVous::factory()->create();
    $service = $rendezVous->service;
    $statut = $rendezVous->statut;

    formulaireDisponibilite(User::factory()->agent()->create(), $service)
        ->set('motif', 'Panne')
        ->set('alternative', 'Mairie annexe')
        ->call('save');

    expect($rendezVous->fresh())->not->toBeNull()
        ->and($rendezVous->fresh()->statut)->toBe($statut);
});
