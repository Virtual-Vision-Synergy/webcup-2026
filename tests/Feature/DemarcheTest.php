<?php

use App\Models\Demarche;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

test('un invité ne peut pas accéder aux demarches', function () {
    $this->get(route('demarches.index'))->assertRedirect(route('login'));
});

test('un utilisateur connecté voit la liste des demarches', function () {
    Demarche::factory()->count(3)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('demarches.index'))
        ->assertOk();
});

test('un utilisateur peut créer : Démarche', function () {
    $user = User::factory()->create();
    $service = Service::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::demarches.form')
        ->set('titre', 'Valeur de test')
        ->set('description', 'Valeur de test')
        ->set('service_id', (string) $service->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Demarche::where('user_id', $user->id)->count())->toBe(1);
    expect(Demarche::where('user_id', $user->id)->value('statut'))->toBe(Demarche::STATUT_OPTIONS[0]);
});

test('le propriétaire peut ouvrir la modification', function () {
    $record = Demarche::factory()->create();

    $this->actingAs($record->user)
        ->get(route('demarches.edit', $record))
        ->assertOk();
});

test('un autre utilisateur ne peut pas modifier', function () {
    $record = Demarche::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('demarches.edit', $record))
        ->assertForbidden();
});

test('un agent peut consulter mais pas supprimer la démarche d\'un habitant', function () {
    $record = Demarche::factory()->create();

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::demarches.show', ['demarche' => $record])
        ->call('delete')
        ->assertForbidden();

    expect(Demarche::find($record->id))->not->toBeNull();
});

test('un admin peut supprimer', function () {
    $record = Demarche::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::demarches.show', ['demarche' => $record])
        ->call('delete');

    expect(Demarche::find($record->id))->toBeNull();
});

test('la liste charge les relations sans requêtes en trop (pas de N+1)', function () {
    Demarche::factory()->count(3)->create();

    Model::preventLazyLoading();

    try {
        $this->actingAs(User::factory()->agent()->create())
            ->get(route('demarches.index'))
            ->assertOk();
    } finally {
        Model::preventLazyLoading(false);
    }
});

test('la relation service doit exister', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::demarches.form')
        ->set('service_id', '999999')
        ->call('save')
        ->assertHasErrors(['service_id']);
});

test('la liste se filtre par service', function () {
    $a = Service::factory()->create();
    $b = Service::factory()->create();
    $dansA = Demarche::factory()->create(['service_id' => $a->id]);
    Demarche::factory()->create(['service_id' => $b->id]);

    $ids = Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::demarches.index')
        ->set('filterServiceId', (string) $a->id)
        ->instance()->items->pluck('id')->all();

    expect($ids)->toBe([$dansA->id]);
});

test('le statut par défaut est la première valeur et n\'est pas remplissable', function () {
    expect((new Demarche)->statut)->toBe(Demarche::STATUT_OPTIONS[0]);

    $record = new Demarche(['statut' => Demarche::STATUT_OPTIONS[1]]);

    expect($record->statut)->toBe(Demarche::STATUT_OPTIONS[0]);
});

test('le formulaire n\'expose pas le statut', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::demarches.form')
        ->assertDontSeeHtml('wire:model="statut"');
});

test('un utilisateur ne peut pas changer le statut de sa propre fiche', function () {
    $record = Demarche::factory()->create(['statut' => Demarche::STATUT_OPTIONS[0]]);

    Livewire::actingAs($record->user)
        ->test('pages::demarches.show', ['demarche' => $record])
        ->call('changerStatut', Demarche::STATUT_OPTIONS[1])
        ->assertForbidden();

    expect($record->refresh()->statut)->toBe(Demarche::STATUT_OPTIONS[0]);
});

test('un admin peut changer le statut', function () {
    $record = Demarche::factory()->create(['statut' => Demarche::STATUT_OPTIONS[0]]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::demarches.show', ['demarche' => $record])
        ->call('changerStatut', Demarche::STATUT_OPTIONS[1]);

    expect($record->refresh()->statut)->toBe(Demarche::STATUT_OPTIONS[1]);
});

test('un statut inconnu est refusé', function () {
    $record = Demarche::factory()->create(['statut' => Demarche::STATUT_OPTIONS[0]]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::demarches.show', ['demarche' => $record])
        ->call('changerStatut', 'valeur-inconnue')
        ->assertStatus(422);

    expect($record->refresh()->statut)->toBe(Demarche::STATUT_OPTIONS[0]);
});

test('le propriétaire voit sa démarche', function () {
    $record = Demarche::factory()->create();

    $this->actingAs($record->user)
        ->get(route('demarches.show', $record))
        ->assertOk()
        ->assertSee($record->titre);
});

test('un autre habitant ne peut pas voir la démarche (ID changé dans l\'URL)', function () {
    $record = Demarche::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('demarches.show', $record))
        ->assertForbidden();
});

test('un habitant ne voit que ses démarches dans la liste', function () {
    $user = User::factory()->create();
    $mienne = Demarche::factory()->for($user)->create();
    Demarche::factory()->create();

    $ids = Livewire::actingAs($user)
        ->test('pages::demarches.index')
        ->instance()->items->pluck('id')->all();

    expect($ids)->toBe([$mienne->id]);
});

test('un agent voit toutes les démarches et peut changer le statut', function () {
    $record = Demarche::factory()->create(['statut' => Demarche::STATUT_OPTIONS[0]]);
    Demarche::factory()->create();
    $agent = User::factory()->agent()->create();

    expect(Livewire::actingAs($agent)->test('pages::demarches.index')->instance()->items->total())->toBe(2);

    Livewire::actingAs($agent)
        ->test('pages::demarches.show', ['demarche' => $record])
        ->call('changerStatut', Demarche::STATUT_OPTIONS[1]);

    expect($record->refresh()->statut)->toBe(Demarche::STATUT_OPTIONS[1]);
});

test('un agent ne peut pas modifier la démarche d\'un habitant', function () {
    $record = Demarche::factory()->create();

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('demarches.edit', $record))
        ->assertForbidden();
});
