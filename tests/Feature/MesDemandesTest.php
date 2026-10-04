<?php

use App\Events\SignalementEtapeAjoutee;
use App\Models\AuditLog;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

test('un invité est redirigé vers la connexion', function () {
    $signalement = Signalement::factory()->create();

    $this->get(route('mes-demandes.index'))->assertRedirect(route('login'));
    $this->get(route('mes-demandes.show', $signalement))->assertRedirect(route('login'));
});

test('le propriétaire voit ses demandes, leur état et les étapes datées', function () {
    $citoyen = User::factory()->citoyen()->create();
    $signalement = Signalement::factory()->for($citoyen)->nouveau()->create([
        'categorie' => 'eclairage',
        'lieu' => 'Rue des Pionniers',
        'created_at' => '2026-10-01 07:30:00',
    ]);

    $this->actingAs($citoyen)
        ->get(route('mes-demandes.index'))
        ->assertOk()
        ->assertSee(['Mes demandes', 'Éclairage public', 'Rue des Pionniers', 'Nouveau', 'Signalement reçu', '01 oct. 2026 · 10:30', 'Voir tout le suivi']);

    $this->get(route('mes-demandes.show', $signalement))
        ->assertOk()
        ->assertSee(['Signalement reçu', 'aria-current="step"', 'Prochaine étape : Prise en charge par les services'], false);
});

test('la liste ne montre aucune demande d\'un autre citoyen', function () {
    $citoyen = User::factory()->citoyen()->create();
    Signalement::factory()->for($citoyen)->create(['lieu' => 'Avenue du Dôme']);
    Signalement::factory()->for(User::factory()->citoyen())->create(['lieu' => 'Impasse du Voisin']);

    $this->actingAs($citoyen)
        ->get(route('mes-demandes.index'))
        ->assertOk()
        ->assertSee('Avenue du Dôme')
        ->assertDontSee('Impasse du Voisin');
});

test('un autre citoyen reçoit un 403 sur le suivi d\'une demande', function () {
    $signalement = Signalement::factory()->create();

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('mes-demandes.show', $signalement))
        ->assertForbidden();
});

test('un agent ou un admin reçoit un 403 sur le suivi d\'une demande d\'un citoyen', function (string $role) {
    $signalement = Signalement::factory()->create();

    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route('mes-demandes.show', $signalement))
        ->assertForbidden();
})->with(['agent', 'admin']);

test('un citoyen sans demande voit l\'état vide et le bouton pour signaler', function () {
    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('mes-demandes.index'))
        ->assertOk()
        ->assertSee(['Vous n&#039;avez encore déposé aucune demande.', 'Signaler un problème', route('signalements.create')], false);
});

test('le dépôt puis chaque changement d\'état ajoutent une étape, dans l\'ordre', function () {
    Event::fake([SignalementEtapeAjoutee::class]);
    $citoyen = User::factory()->citoyen()->create();
    $signalement = Signalement::factory()->for($citoyen)->nouveau()->create();

    Event::assertDispatched(SignalementEtapeAjoutee::class, fn ($event) => $event->signalement->is($signalement) && $event->statut === 'nouveau');
    expect(array_column($signalement->fresh()->chronologie(), 'label'))->toBe(['Signalement reçu']);

    // L'agent change l'état depuis l'écran du signalement, comme en vrai.
    $agent = User::factory()->agent()->create();
    $this->travel(1)->hours();
    Livewire::actingAs($agent)->test('pages::signalements.show', ['signalement' => $signalement])->call('changerStatut', 'en_cours');
    $this->travel(1)->hours();
    Livewire::actingAs($agent)->test('pages::signalements.show', ['signalement' => $signalement])->call('changerStatut', 'resolu');

    Event::assertDispatchedTimes(SignalementEtapeAjoutee::class, 3);

    $chronologie = $signalement->fresh()->load('etapes')->chronologie();
    expect(array_column($chronologie, 'statut'))->toBe(['nouveau', 'en_cours', 'resolu'])
        ->and(array_column($chronologie, 'courant'))->toBe([false, false, true])
        ->and($chronologie[1]['date']->lessThan($chronologie[2]['date']))->toBeTrue();
});

test('une mise à jour sans changement d\'état n\'ajoute aucune étape', function () {
    $signalement = Signalement::factory()->nouveau()->create();
    Event::fake([SignalementEtapeAjoutee::class]);

    $signalement->update(['description' => 'Description complétée par l’habitant.']);

    Event::assertNotDispatched(SignalementEtapeAjoutee::class);
    expect(AuditLog::where('action', 'status_changed')->count())->toBe(0)
        ->and($signalement->fresh()->chronologie())->toHaveCount(1);
});

test('un signalement sans historique affiche quand même son état actuel', function () {
    $signalement = Signalement::factory()->create(['statut' => 'resolu']);

    expect(array_column($signalement->chronologie(), 'statut'))->toBe(['nouveau', 'resolu']);
});

test('l\'assignation de masse de user_id et statut est ignorée', function () {
    $citoyen = User::factory()->citoyen()->create();
    $autre = User::factory()->citoyen()->create();

    $signalement = new Signalement([
        'categorie' => 'voirie',
        'description' => 'Nid-de-poule.',
        'lieu' => 'Avenue du Dôme',
        'user_id' => $autre->id,
        'statut' => 'resolu',
        'signalement_id' => 99,
    ]);

    expect($signalement->user_id)->toBeNull()
        ->and($signalement->statut)->toBe('nouveau')
        ->and($signalement->getAttributes())->not->toHaveKey('signalement_id');

    $signalement->user()->associate($citoyen)->save();
    expect($signalement->fresh()->user->is($citoyen))->toBeTrue();
});

test('une description malveillante est affichée échappée', function () {
    $citoyen = User::factory()->citoyen()->create();
    $signalement = Signalement::factory()->for($citoyen)->create(['description' => '<script>alert(1)</script>']);

    $this->actingAs($citoyen)->get(route('mes-demandes.index'))
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

    $this->get(route('mes-demandes.show', $signalement))
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

test('le nom de l\'agent n\'apparaît jamais dans le suivi citoyen', function () {
    $citoyen = User::factory()->citoyen()->create();
    $signalement = Signalement::factory()->for($citoyen)->nouveau()->create();
    $agent = User::factory()->agent()->create(['name' => 'Agent Confidentiel']);

    Livewire::actingAs($agent)->test('pages::signalements.show', ['signalement' => $signalement])->call('changerStatut', 'en_cours');

    $this->actingAs($citoyen)->get(route('mes-demandes.show', $signalement))
        ->assertOk()
        ->assertSee(['Pris en charge par les services', 'Par la Mairie de Nova Terra'])
        ->assertDontSee(['Agent Confidentiel', $agent->email]);
});
