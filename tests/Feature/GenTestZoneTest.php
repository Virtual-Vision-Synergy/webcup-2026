<?php

use App\Models\GenTestZone;
use App\Models\User;
use Livewire\Livewire;

test('un invité ne peut pas accéder aux gen-test-zones', function () {
    $this->get(route('gen-test-zones.index'))->assertRedirect(route('login'));
});

test('un utilisateur connecté voit la liste des gen-test-zones', function () {
    GenTestZone::factory()->count(3)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('gen-test-zones.index'))
        ->assertOk();
});

test('un utilisateur peut créer : Gen Test Zone', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::gen-test-zones.form')
        ->set('nom', 'Valeur de test')
        ->call('save')
        ->assertHasNoErrors();

    expect(GenTestZone::where('user_id', $user->id)->count())->toBe(1);
});

test('le propriétaire peut ouvrir la modification', function () {
    $record = GenTestZone::factory()->create();

    $this->actingAs($record->user)
        ->get(route('gen-test-zones.edit', $record))
        ->assertOk();
});

test('un autre utilisateur ne peut pas modifier', function () {
    $record = GenTestZone::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('gen-test-zones.edit', $record))
        ->assertForbidden();
});

test('un autre utilisateur ne peut pas supprimer', function () {
    $record = GenTestZone::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::gen-test-zones.show', ['genTestZone' => $record])
        ->call('delete')
        ->assertForbidden();

    expect(GenTestZone::find($record->id))->not->toBeNull();
});

test('un admin peut supprimer', function () {
    $record = GenTestZone::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::gen-test-zones.show', ['genTestZone' => $record])
        ->call('delete');

    expect(GenTestZone::find($record->id))->toBeNull();
});
