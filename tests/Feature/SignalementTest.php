<?php

use App\Models\Signalement;
use App\Models\User;
use Livewire\Livewire;

test('un invité ne peut pas accéder aux signalements', function () {
    $this->get(route('signalements.index'))->assertRedirect(route('login'));
});

test('un utilisateur connecté voit la liste des signalements', function () {
    Signalement::factory()->count(3)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('signalements.index'))
        ->assertOk();
});

test('un utilisateur peut créer : Signalement', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::signalements.form')
        ->set('titre', 'Valeur de test')
        ->set('niveau', Signalement::NIVEAU_OPTIONS[0])
        ->set('zone', 'Valeur de test')
        ->set('date_incident', '2026-10-03')
        ->call('save')
        ->assertHasNoErrors();

    expect(Signalement::where('user_id', $user->id)->count())->toBe(1);
});

test('le propriétaire peut ouvrir la modification', function () {
    $record = Signalement::factory()->create();

    $this->actingAs($record->user)
        ->get(route('signalements.edit', $record))
        ->assertOk();
});

test('un autre utilisateur ne peut pas modifier', function () {
    $record = Signalement::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('signalements.edit', $record))
        ->assertForbidden();
});

test('un autre utilisateur ne peut pas supprimer', function () {
    $record = Signalement::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::signalements.show', ['signalement' => $record])
        ->call('delete')
        ->assertForbidden();

    expect(Signalement::find($record->id))->not->toBeNull();
});

test('un admin peut supprimer', function () {
    $record = Signalement::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::signalements.show', ['signalement' => $record])
        ->call('delete');

    expect(Signalement::find($record->id))->toBeNull();
});
