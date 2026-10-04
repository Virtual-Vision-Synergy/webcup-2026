<?php

use App\Filament\Resources\GenTestFiches\Pages\ListGenTestFiches;
use App\Models\GenTestFiche;
use App\Models\GenTestZone;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

test('un invité voit la liste des gen-test-fiches', function () {
    GenTestFiche::factory()->count(3)->create(['statut' => GenTestFiche::STATUT_OPTIONS[1]]);

    $this->get(route('gen-test-fiches.index'))->assertOk();
});

test('un invité voit le détail d\'une fiche', function () {
    $record = GenTestFiche::factory()->create(['statut' => GenTestFiche::STATUT_OPTIONS[1]]);

    $this->get(route('gen-test-fiches.show', $record))->assertOk();
});

test('un invité ne voit ni les liens de modification ni l\'auteur', function () {
    $auteur = User::factory()->create(['name' => 'Auteur Confidentiel']);
    $record = GenTestFiche::factory()->for($auteur)->create(['statut' => GenTestFiche::STATUT_OPTIONS[1]]);

    $this->get(route('gen-test-fiches.index'))
        ->assertOk()
        ->assertDontSee(route('gen-test-fiches.create'))
        ->assertDontSee(route('gen-test-fiches.edit', $record))
        ->assertDontSee('Auteur Confidentiel');

    $this->get(route('gen-test-fiches.show', $record))
        ->assertOk()
        ->assertDontSee(route('gen-test-fiches.edit', $record))
        ->assertDontSee('Auteur Confidentiel');
});

test('un invité est redirigé vers la connexion sur le formulaire', function () {
    $record = GenTestFiche::factory()->create(['statut' => GenTestFiche::STATUT_OPTIONS[1]]);

    $this->get(route('gen-test-fiches.create'))->assertRedirect(route('login'));
    $this->get(route('gen-test-fiches.edit', $record))->assertRedirect(route('login'));
});

test('un invité ne peut ni créer, ni modifier, ni supprimer', function () {
    $record = GenTestFiche::factory()->create(['statut' => GenTestFiche::STATUT_OPTIONS[1]]);

    expect(Gate::allows('create', GenTestFiche::class))->toBeFalse()
        ->and(Gate::allows('update', $record))->toBeFalse()
        ->and(Gate::allows('delete', $record))->toBeFalse();

    Livewire::test('pages::gen-test-fiches.index')->call('delete', $record->id)->assertForbidden();

    expect(GenTestFiche::find($record->id))->not->toBeNull();
});

test('un utilisateur connecté voit la liste des gen-test-fiches', function () {
    GenTestFiche::factory()->count(3)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('gen-test-fiches.index'))
        ->assertOk();
});

test('un utilisateur peut créer : Gen Test Fiche', function () {
    $user = User::factory()->create();
    $genTestZone = GenTestZone::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::gen-test-fiches.form')
        ->set('titre', 'Valeur de test')
        ->set('niveau', GenTestFiche::NIVEAU_OPTIONS[0])
        ->set('gen_test_zone_id', (string) $genTestZone->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(GenTestFiche::where('user_id', $user->id)->count())->toBe(1);
    expect(GenTestFiche::where('user_id', $user->id)->value('statut'))->toBe(GenTestFiche::STATUT_OPTIONS[0]);
});

test('le propriétaire peut ouvrir la modification', function () {
    $record = GenTestFiche::factory()->create();

    $this->actingAs($record->user)
        ->get(route('gen-test-fiches.edit', $record))
        ->assertOk();
});

test('un autre utilisateur ne peut pas modifier', function () {
    $record = GenTestFiche::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('gen-test-fiches.edit', $record))
        ->assertForbidden();
});

test('un autre utilisateur ne peut pas supprimer', function () {
    $record = GenTestFiche::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::gen-test-fiches.show', ['genTestFiche' => $record])
        ->call('delete')
        ->assertForbidden();

    expect(GenTestFiche::find($record->id))->not->toBeNull();
});

test('un admin peut supprimer', function () {
    $record = GenTestFiche::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::gen-test-fiches.show', ['genTestFiche' => $record])
        ->call('delete');

    expect(GenTestFiche::find($record->id))->toBeNull();
});

test('la liste charge les relations sans requêtes en trop (pas de N+1)', function () {
    GenTestFiche::factory()->count(3)->create();

    Model::preventLazyLoading();

    try {
        $this->actingAs(User::factory()->create())
            ->get(route('gen-test-fiches.index'))
            ->assertOk();
    } finally {
        Model::preventLazyLoading(false);
    }
});

test('la relation genTestZone doit exister', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::gen-test-fiches.form')
        ->set('gen_test_zone_id', '999999')
        ->call('save')
        ->assertHasErrors(['gen_test_zone_id']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::gen-test-fiches.form')
        ->set('gen_test_zone_id', '')
        ->call('save')
        ->assertHasErrors(['gen_test_zone_id' => 'required']);
});

test('la liste se filtre par genTestZone', function () {
    $a = GenTestZone::factory()->create();
    $b = GenTestZone::factory()->create();
    $dansA = GenTestFiche::factory()->create(['gen_test_zone_id' => $a->id]);
    GenTestFiche::factory()->create(['gen_test_zone_id' => $b->id]);

    $ids = Livewire::actingAs(User::factory()->create())
        ->test('pages::gen-test-fiches.index')
        ->set('filterGenTestZoneId', (string) $a->id)
        ->instance()->items->pluck('id')->all();

    expect($ids)->toBe([$dansA->id]);
});

test('le statut par défaut est la première valeur et n\'est pas remplissable', function () {
    expect((new GenTestFiche)->statut)->toBe(GenTestFiche::STATUT_OPTIONS[0]);

    $record = new GenTestFiche(['statut' => GenTestFiche::STATUT_OPTIONS[1]]);

    expect($record->statut)->toBe(GenTestFiche::STATUT_OPTIONS[0]);
});

test('le formulaire n\'expose pas le statut', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::gen-test-fiches.form')
        ->assertDontSeeHtml('wire:model="statut"');
});

test('un utilisateur ne peut pas changer le statut de sa propre fiche', function () {
    $record = GenTestFiche::factory()->create(['statut' => GenTestFiche::STATUT_OPTIONS[0]]);

    Livewire::actingAs($record->user)
        ->test('pages::gen-test-fiches.show', ['genTestFiche' => $record])
        ->call('changerStatut', GenTestFiche::STATUT_OPTIONS[1])
        ->assertForbidden();

    expect($record->refresh()->statut)->toBe(GenTestFiche::STATUT_OPTIONS[0]);
});

test('un admin peut changer le statut', function () {
    $record = GenTestFiche::factory()->create(['statut' => GenTestFiche::STATUT_OPTIONS[0]]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::gen-test-fiches.show', ['genTestFiche' => $record])
        ->call('changerStatut', GenTestFiche::STATUT_OPTIONS[1]);

    expect($record->refresh()->statut)->toBe(GenTestFiche::STATUT_OPTIONS[1]);
});

test('un statut inconnu est refusé', function () {
    $record = GenTestFiche::factory()->create(['statut' => GenTestFiche::STATUT_OPTIONS[0]]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::gen-test-fiches.show', ['genTestFiche' => $record])
        ->call('changerStatut', 'valeur-inconnue')
        ->assertStatus(422);

    expect($record->refresh()->statut)->toBe(GenTestFiche::STATUT_OPTIONS[0]);
});

test('un invité ne voit que les fiches déjà validées', function () {
    $enAttente = GenTestFiche::factory()->create(['statut' => GenTestFiche::STATUT_OPTIONS[0]]);
    $validee = GenTestFiche::factory()->create(['statut' => GenTestFiche::STATUT_OPTIONS[1]]);

    $this->get(route('gen-test-fiches.show', $validee))->assertOk();
    $this->get(route('gen-test-fiches.show', $enAttente))->assertForbidden();

    $ids = Livewire::test('pages::gen-test-fiches.index')->instance()->items->pluck('id')->all();

    expect($ids)->toBe([$validee->id]);
});

test('un non-admin n\'accède pas à la ressource Filament', function () {
    $record = GenTestFiche::factory()->create();

    $this->actingAs(User::factory()->create());

    $this->get('/admin/gen-test-fiches')->assertForbidden();
    $this->get('/admin/gen-test-fiches/create')->assertForbidden();
    $this->get('/admin/gen-test-fiches/'.$record->getKey().'/edit')->assertForbidden();
});

test('un admin ouvre la liste, la création et la vue Filament', function () {
    $record = GenTestFiche::factory()->create();

    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/gen-test-fiches')->assertOk();
    $this->get('/admin/gen-test-fiches/create')->assertOk();
    $this->get('/admin/gen-test-fiches/'.$record->getKey())->assertOk();
    $this->get('/admin/gen-test-fiches/'.$record->getKey().'/edit')->assertOk();
});

test('un admin change le statut depuis Filament', function () {
    $record = GenTestFiche::factory()->create(['statut' => GenTestFiche::STATUT_OPTIONS[0]]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListGenTestFiches::class)
        ->callAction(TestAction::make('changerStatut')->table($record), ['statut' => GenTestFiche::STATUT_OPTIONS[1]])
        ->assertHasNoFormErrors();

    expect($record->refresh()->statut)->toBe(GenTestFiche::STATUT_OPTIONS[1]);
});
