<?php

use App\Models\AuditLog;
use App\Models\Demarche;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use App\Policies\DemarchePolicy;
use Livewire\Livewire;

/*
| F70 : un agent n'accède qu'aux données des services auxquels il est rattaché ; l'admin à tout.
| Contrôlé dans les policies et dans chaque requête (visibleTo), chaque refus est journalisé (F47).
*/

beforeEach(function () {
    $this->etatCivil = Service::factory()->create(['nom' => 'État civil']);
    $this->social = Service::factory()->create(['nom' => 'Action sociale']);

    $this->agentEtatCivil = User::factory()->agentDe($this->etatCivil)->create();
    $this->admin = User::factory()->admin()->create();

    $this->demandeEtatCivil = Demarche::factory()->for($this->etatCivil)->create(['titre' => 'Acte de naissance', 'statut' => 'deposee']);
    $this->demandeSociale = Demarche::factory()->for($this->social)->create(['titre' => 'Aide alimentaire', 'statut' => 'deposee']);
});

test('un agent ouvre une démarche de son service', function () {
    $this->actingAs($this->agentEtatCivil)
        ->get(route('demarches.show', $this->demandeEtatCivil))
        ->assertOk()
        ->assertSee('Acte de naissance');
});

test('un agent reçoit un 403 explicite sur la démarche d’un autre service, et le refus est journalisé', function () {
    $this->actingAs($this->agentEtatCivil)
        ->get(route('demarches.show', $this->demandeSociale))
        ->assertForbidden()
        ->assertSee('Ce dossier relève d’un service auquel vous n’êtes pas rattaché')
        ->assertDontSee('Aide alimentaire');

    $refus = AuditLog::query()->where('action', 'access_denied')->sole();

    expect($refus->actor_id)->toBe($this->agentEtatCivil->id)
        ->and($refus->subject_type)->toBe('Demarche')
        ->and($refus->subject_id)->toBe($this->demandeSociale->id)
        ->and($refus->changes['motif']['apres'])->toBe(DemarchePolicy::MOTIF_AUTRE_SERVICE)
        ->and($refus->changes['url']['apres'])->toBe('/demarches/'.$this->demandeSociale->id)
        ->and($refus->ip)->not->toBeNull();
});

test('un agent ne peut ni modifier ni supprimer la démarche d’un autre service', function () {
    $this->actingAs($this->agentEtatCivil)
        ->get(route('demarches.edit', $this->demandeSociale))
        ->assertForbidden();

    expect(AuditLog::query()->where('action', 'access_denied')->count())->toBe(1);
});

test('un agent ne peut pas changer l’état d’une demande d’un autre service depuis la liste', function () {
    Livewire::actingAs($this->agentEtatCivil)
        ->test('pages::agent.demandes')
        ->call('changerStatut', $this->demandeSociale->id, 'traitee')
        ->assertForbidden();

    expect($this->demandeSociale->fresh()->statut)->toBe('deposee')
        ->and(AuditLog::query()->where('action', 'access_denied')->where('subject_id', $this->demandeSociale->id)->count())->toBe(1);
});

test('un agent ne marque pas un rendez-vous d’un autre service', function () {
    $rendezVous = RendezVous::factory()->create(['service_id' => $this->social->id]);

    Livewire::actingAs($this->agentEtatCivil)
        ->test('pages::agent.rendez-vous')
        ->call('changerStatut', $rendezVous->id, 'honore')
        ->assertForbidden();

    expect($rendezVous->fresh()->statut)->toBe('confirme');
});

test('la liste, la recherche et les compteurs d’un agent ne contiennent que son service', function () {
    $liste = Livewire::actingAs($this->agentEtatCivil)
        ->test('pages::agent.demandes')
        ->set('search', 'a')
        ->assertSee('Acte de naissance')
        ->assertDontSee('Aide alimentaire');

    expect(array_sum($liste->instance()->compteurs))->toBe(1);

    Livewire::actingAs($this->agentEtatCivil)
        ->test('compteur-demandes-attente')
        ->assertSeeInOrder(['1', 'demande en attente de prise en charge']);

    $tableau = Livewire::actingAs($this->agentEtatCivil)->test('pages::agent.tableau-de-bord');
    expect($tableau->instance()->compteurs['total'])->toBe(1)
        ->and($tableau->instance()->dernieresDemandes->pluck('id')->all())->toBe([$this->demandeEtatCivil->id]);

    $index = Livewire::actingAs($this->agentEtatCivil)->test('pages::demarches.index');
    expect($index->instance()->items->pluck('id')->all())->toBe([$this->demandeEtatCivil->id]);
});

test('le journal et l’historique d’un agent ne révèlent pas les dossiers des autres services', function () {
    $this->demandeEtatCivil->changerStatut('en_cours');
    $this->demandeSociale->changerStatut('en_cours');

    Livewire::actingAs($this->agentEtatCivil)
        ->test('pages::agent.audit.index')
        ->assertSee('Acte de naissance')
        ->assertDontSee('Aide alimentaire');

    $entreeSociale = AuditLog::query()->where('subject_type', 'Demarche')->where('subject_id', $this->demandeSociale->id)->firstOrFail();

    $this->actingAs($this->agentEtatCivil)->get(route('agent.audit.show', $entreeSociale))->assertForbidden();
    $this->actingAs($this->agentEtatCivil)
        ->get(route('agent.history.show', ['type' => 'demarche', 'id' => $this->demandeSociale->id]))
        ->assertForbidden();
});

test('une démarche sans service est réservée à l’admin', function () {
    $sansService = Demarche::factory()->create(['service_id' => null]);

    $this->actingAs($this->agentEtatCivil)->get(route('demarches.show', $sansService))->assertForbidden();
    $this->actingAs($this->admin)->get(route('demarches.show', $sansService))->assertOk();
});

test('un agent sans service ne voit aucune demande', function () {
    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::agent.demandes')
        ->assertDontSee('Acte de naissance')
        ->assertDontSee('Aide alimentaire');
});

test('l’admin accède aux démarches de tous les services', function () {
    $this->actingAs($this->admin)->get(route('demarches.show', $this->demandeSociale))->assertOk();

    Livewire::actingAs($this->admin)
        ->test('pages::agent.demandes')
        ->assertSee('Acte de naissance')
        ->assertSee('Aide alimentaire');
});

test('un citoyen reçoit un 403 journalisé sur l’espace agent et sur la démarche d’un autre', function () {
    $citoyen = User::factory()->citoyen()->create();

    $this->actingAs($citoyen)->get(route('agent.demandes'))->assertForbidden();
    $this->actingAs($citoyen)->get(route('demarches.show', $this->demandeSociale))->assertForbidden();

    expect(AuditLog::query()->where('action', 'access_denied')->where('actor_id', $citoyen->id)->count())->toBe(2);
});

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('demarches.show', $this->demandeEtatCivil))->assertRedirect(route('login'));
});

test('l’admin rattache un agent à des services et le changement est journalisé', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::agent.citizens.show', ['user' => $this->agentEtatCivil])
        ->set('servicesCouverts', [(string) $this->etatCivil->id, (string) $this->social->id])
        ->call('enregistrerServices')
        ->assertHasNoErrors();

    expect($this->agentEtatCivil->services()->pluck('services.id')->sort()->values()->all())
        ->toBe([$this->etatCivil->id, $this->social->id])
        ->and(AuditLog::query()->where('action', 'services_changed')->where('subject_id', $this->agentEtatCivil->id)->exists())->toBeTrue();
});

test('un agent ne peut pas se rattacher lui-même à un service', function () {
    Livewire::actingAs($this->agentEtatCivil)
        ->test('pages::agent.citizens.show', ['user' => $this->agentEtatCivil])
        ->assertForbidden();

    expect($this->agentEtatCivil->can('assignServices', $this->agentEtatCivil))->toBeFalse()
        ->and($this->agentEtatCivil->services()->pluck('services.id')->all())->toBe([$this->etatCivil->id]);
});
