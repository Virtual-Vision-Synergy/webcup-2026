<?php

use App\Exceptions\CreneauIndisponible;
use App\Models\ActionLog;
use App\Models\CreneauRendezVous;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use App\Services\PriseDeRendezVous;
use Livewire\Livewire;

/*
| F39 — Prise de rendez-vous. Horloge figée : lundi 5 octobre 2026, 08:00 UTC (11 h 00 à Nova Terra, UTC+3).
*/

beforeEach(function () {
    $this->travelTo(now()->parse('2026-10-05 08:00:00', 'UTC'));

    $this->etatCivil = Service::factory()->create([
        'nom' => 'État civil',
        'lieu_rendez_vous' => 'Hôtel de ville, guichet 2',
        'duree_rendez_vous' => 30,
        'pieces_a_fournir' => "Pièce d'identité\nLivret de famille",
    ]);
});

function creneauDe(Service $service, string $debutUtc, int $minutes = 30): CreneauRendezVous
{
    return CreneauRendezVous::factory()->a($debutUtc, $minutes)->for($service)->create();
}

test('un invité est redirigé vers la connexion sur toutes les routes', function (string $route, bool $avecRendezVous) {
    $parametres = $avecRendezVous ? [RendezVous::factory()->create()] : [];

    $this->get(route($route, $parametres))->assertRedirect(route('login'));
})->with([
    ['appointments.index', false],
    ['appointments.create', false],
    ['appointments.show', true],
    ['agent.appointments.index', false],
]);

test('un citoyen réserve un créneau libre et voit le récapitulatif complet', function () {
    $citoyen = User::factory()->citoyen()->create();
    $creneau = creneauDe($this->etatCivil, '2026-10-06 06:30:00');

    Livewire::actingAs($citoyen)
        ->test('pages::rendez-vous.form')
        ->call('choisirService', $this->etatCivil->slug)
        ->assertSee('Mardi 6 octobre 2026')
        ->assertSee('09 h 30 à 10 h 00')
        ->call('choisirCreneau', $creneau->id)
        ->assertSee(['État civil', 'Mardi 6 octobre 2026', 'De 09 h 30 à 10 h 00', 'heure de Nova Terra', 'UTC+03:00', 'Hôtel de ville, guichet 2', "Pièce d'identité", 'Livret de famille'])
        ->set('motif', 'Copie d’acte de naissance')
        ->call('confirmer')
        ->assertHasNoErrors()
        ->assertRedirect(route('appointments.show', RendezVous::sole()));

    $rendezVous = RendezVous::sole();
    expect($rendezVous->user_id)->toBe($citoyen->id)
        ->and($rendezVous->statut)->toBe('confirme')
        ->and($rendezVous->motif)->toBe('Copie d’acte de naissance')
        ->and($creneau->fresh()->rendez_vous_id)->toBe($rendezVous->id)
        ->and(ActionLog::where('action', 'rendez_vous_reserve')->where('subject_id', $rendezVous->id)->exists())->toBeTrue();

    $this->actingAs($citoyen)
        ->withSession(['rendez-vous-confirme' => true])
        ->get(route('appointments.show', $rendezVous))
        ->assertOk()
        ->assertSee(['Rendez-vous confirmé', 'Mardi 6 octobre 2026', '09 h 30', 'heure de Nova Terra', 'Hôtel de ville, guichet 2', 'Pensez à apporter']);
});

test('un créneau réservé n’est plus proposé', function () {
    $pris = creneauDe($this->etatCivil, '2026-10-06 06:30:00');
    creneauDe($this->etatCivil, '2026-10-06 07:00:00');
    RendezVous::factory()->create(['creneau_id' => $pris->id]);

    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::rendez-vous.form', ['serviceSlug' => $this->etatCivil->slug])
        ->assertSee('10 h 00 à 10 h 30')
        ->assertDontSee('09 h 30 à 10 h 00');
});

test('le sélecteur de date ne propose que les horaires libres du jour choisi', function () {
    creneauDe($this->etatCivil, '2026-10-06 06:30:00');
    $mercredi = creneauDe($this->etatCivil, '2026-10-07 11:00:00');

    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::rendez-vous.form', ['serviceSlug' => $this->etatCivil->slug])
        ->assertSet('jour', '2026-10-06')
        ->set('jour', '2026-10-07')
        ->assertSee('Mercredi 7 octobre 2026')
        ->assertSee('14 h 00 à 14 h 30')
        ->assertDontSee('09 h 30 à 10 h 00')
        ->call('validerCreneau')
        ->assertHasErrors(['creneauSelectionne'])
        ->set('creneauSelectionne', (string) $mercredi->id)
        ->call('validerCreneau')
        ->assertSet('creneauId', (string) $mercredi->id)
        ->assertSee('Vérifiez votre rendez-vous');
});

test('un jour sans créneau libre propose le prochain jour disponible', function () {
    creneauDe($this->etatCivil, '2026-10-08 06:30:00');

    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::rendez-vous.form', ['serviceSlug' => $this->etatCivil->slug])
        ->set('jour', '2026-10-06')
        ->assertSee('Aucun créneau libre le mardi 6 octobre 2026')
        ->assertSee('jeudi 8 octobre 2026')
        ->call('allerAuJour', '2026-10-08')
        ->assertSee('09 h 30 à 10 h 00');
});

test('la seconde réservation d’un même créneau échoue avec un message clair', function () {
    $creneau = creneauDe($this->etatCivil, '2026-10-06 06:30:00');
    $premier = User::factory()->citoyen()->create();

    $page = Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::rendez-vous.form')
        ->call('choisirService', $this->etatCivil->slug)
        ->call('choisirCreneau', $creneau->id);

    app(PriseDeRendezVous::class)->reserver($premier, $this->etatCivil, $creneau->id);

    $page->call('confirmer')
        ->assertSee(CreneauIndisponible::DEJA_PRIS)
        ->assertSet('creneauId', '');

    expect(RendezVous::count())->toBe(1)
        ->and(RendezVous::sole()->user_id)->toBe($premier->id);
});

test('un créneau passé, trop proche ou d’un autre service est refusé', function (string $debutUtc, bool $autreService) {
    $service = $autreService ? Service::factory()->create(['duree_rendez_vous' => 30]) : $this->etatCivil;
    $creneau = creneauDe($service, $debutUtc);

    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::rendez-vous.form', ['serviceSlug' => $this->etatCivil->slug])
        ->set('creneauId', (string) $creneau->id)
        ->call('confirmer')
        ->assertSee(CreneauIndisponible::INVALIDE);

    expect(RendezVous::count())->toBe(0);
})->with([
    'passé' => ['2026-10-05 06:00:00', false],
    'dans moins de 2 h' => ['2026-10-05 09:00:00', false],
    'autre service' => ['2026-10-06 06:30:00', true],
]);

test('un citoyen ne peut pas avoir deux rendez-vous qui se chevauchent', function () {
    $citoyen = User::factory()->citoyen()->create();
    $urbanisme = Service::factory()->create(['duree_rendez_vous' => 45]);
    RendezVous::factory()->for($citoyen)->create(['creneau_id' => creneauDe($urbanisme, '2026-10-06 06:15:00', 45)->id]);
    $creneau = creneauDe($this->etatCivil, '2026-10-06 06:30:00');

    expect(fn () => app(PriseDeRendezVous::class)->reserver($citoyen, $this->etatCivil, $creneau->id))
        ->toThrow(CreneauIndisponible::class, CreneauIndisponible::CHEVAUCHEMENT);
    expect($creneau->fresh()->rendez_vous_id)->toBeNull();
});

test('un citoyen reçoit un 403 sur la fiche et l’annulation du rendez-vous d’un autre', function () {
    $rendezVousDeB = RendezVous::factory()->create(['creneau_id' => creneauDe($this->etatCivil, '2026-10-06 06:30:00')->id]);
    $citoyenA = User::factory()->citoyen()->create();

    $this->actingAs($citoyenA)->get(route('appointments.show', $rendezVousDeB))->assertForbidden();

    expect($citoyenA->can('cancel', $rendezVousDeB))->toBeFalse()
        ->and($rendezVousDeB->fresh()->statut)->toBe('confirme');
});

test('« Mes rendez-vous » ne liste que ceux du citoyen connecté', function () {
    $citoyen = User::factory()->citoyen()->create();
    RendezVous::factory()->for($citoyen)->create(['creneau_id' => creneauDe($this->etatCivil, '2026-10-06 06:30:00')->id]);
    $autreService = Service::factory()->create(['nom' => 'Voirie et propreté', 'duree_rendez_vous' => 30]);
    RendezVous::factory()->create(['creneau_id' => creneauDe($autreService, '2026-10-06 06:30:00')->id]);

    $this->actingAs($citoyen)
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSee('État civil')
        ->assertDontSee('Voirie et propreté');
});

test('l’annulation libère le créneau, qui est de nouveau proposé', function () {
    $citoyen = User::factory()->citoyen()->create();
    $creneau = creneauDe($this->etatCivil, '2026-10-06 06:30:00');
    $rendezVous = RendezVous::factory()->for($citoyen)->create(['creneau_id' => $creneau->id]);

    Livewire::actingAs($citoyen)
        ->test('pages::rendez-vous.show', ['rendezVous' => $rendezVous])
        ->call('annuler')
        ->assertHasNoErrors();

    $rendezVous->refresh();
    expect($rendezVous->statut)->toBe('annule')
        ->and($rendezVous->annule_le)->not->toBeNull()
        ->and($creneau->fresh()->rendez_vous_id)->toBeNull();

    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::rendez-vous.form', ['serviceSlug' => $this->etatCivil->slug])
        ->assertSee('09 h 30 à 10 h 00');
});

test('un autre citoyen ne peut pas annuler un rendez-vous qui n’est pas le sien', function () {
    $rendezVous = RendezVous::factory()->create(['creneau_id' => creneauDe($this->etatCivil, '2026-10-06 06:30:00')->id]);

    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::rendez-vous.show', ['rendezVous' => $rendezVous])
        ->assertForbidden();

    expect($rendezVous->fresh()->statut)->toBe('confirme');
});

test('l’annulation d’un rendez-vous passé est refusée', function () {
    $citoyen = User::factory()->citoyen()->create();
    $rendezVous = RendezVous::factory()->for($citoyen)->create(['creneau_id' => creneauDe($this->etatCivil, '2026-10-06 06:30:00')->id]);
    $page = Livewire::actingAs($citoyen)->test('pages::rendez-vous.show', ['rendezVous' => $rendezVous]);

    $this->travelTo(now()->parse('2026-10-06 07:00:00', 'UTC'));

    $page->call('annuler')->assertForbidden();
    expect($rendezVous->fresh()->statut)->toBe('confirme');
});

test('user_id, statut, creneau_id et service_id envoyés en masse sont ignorés', function () {
    $autre = User::factory()->create();
    $rendezVous = new RendezVous([
        'motif' => 'Motif',
        'user_id' => $autre->id,
        'statut' => 'honore',
        'creneau_id' => 999,
        'service_id' => 999,
        'annule_le' => now(),
    ]);

    expect($rendezVous->getAttributes())->toBe(['statut' => 'confirme', 'motif' => 'Motif']);
    expect((new CreneauRendezVous(['rendez_vous_id' => 5]))->rendez_vous_id)->toBeNull();
});

test('un citoyen reçoit un 403 sur l’agenda agent', function () {
    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('agent.appointments.index'))
        ->assertForbidden();
});

test('un agent ne voit que les rendez-vous du jour, triés par heure', function () {
    // Aujourd'hui en heure locale : 5 octobre ; 21:30 UTC le 4 = 00 h 30 le 5 à Nova Terra.
    $tot = RendezVous::factory()->create(['creneau_id' => creneauDe($this->etatCivil, '2026-10-04 21:30:00')->id, 'motif' => 'Tout premier']);
    RendezVous::factory()->create(['creneau_id' => creneauDe($this->etatCivil, '2026-10-05 12:00:00')->id, 'motif' => 'Après-midi']);
    RendezVous::factory()->create(['creneau_id' => creneauDe($this->etatCivil, '2026-10-05 21:00:00')->id, 'motif' => 'Demain minuit']);
    RendezVous::factory()->create(['creneau_id' => creneauDe($this->etatCivil, '2026-10-04 20:30:00')->id, 'motif' => 'Hier soir']);

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.appointments.index'))
        ->assertOk()
        ->assertSeeInOrder(['00 h 30', 'Tout premier', '15 h 00', 'Après-midi'])
        ->assertSee($tot->user->name)
        ->assertDontSee('Demain minuit')
        ->assertDontSee('Hier soir');
});

test('un agent marque un rendez-vous honoré, pas un citoyen', function () {
    $rendezVous = RendezVous::factory()->create(['creneau_id' => creneauDe($this->etatCivil, '2026-10-05 12:00:00')->id]);

    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::agent.rendez-vous')
        ->assertForbidden();

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::agent.rendez-vous')
        ->call('changerStatut', $rendezVous->id, 'honore')
        ->assertHasNoErrors();

    expect($rendezVous->fresh()->statut)->toBe('honore');
});

test('un créneau à 06:30 UTC s’affiche à 09 h 30, heure de Nova Terra', function () {
    $creneau = creneauDe($this->etatCivil, '2026-10-06 06:30:00');

    expect($creneau->libelleComplet())->toBe('Mardi 6 octobre 2026 — 09 h 30 à 10 h 00 (heure de Nova Terra)');
});

test('la génération des créneaux est idempotente et respecte jours ouvrés et horaires', function () {
    Service::factory()->create(['duree_rendez_vous' => null]);

    $this->artisan('appointments:generate-slots', ['--days' => 2])->assertSuccessful();
    $nombre = CreneauRendezVous::count();
    $this->artisan('appointments:generate-slots', ['--days' => 2])->assertSuccessful();

    // Lundi 5 après 11 h locale (11 h 30–12 h 00 + 5 l'après-midi) et mardi 6 (6 le matin + 5 l'après-midi).
    expect($nombre)->toBe(17)
        ->and(CreneauRendezVous::count())->toBe($nombre)
        ->and(CreneauRendezVous::where('service_id', '!=', $this->etatCivil->id)->count())->toBe(0)
        ->and(CreneauRendezVous::orderBy('debut')->first()->libelleComplet())->toBe('Lundi 5 octobre 2026 — 11 h 30 à 12 h 00 (heure de Nova Terra)');
});
