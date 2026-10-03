<?php

use App\Models\Actualite;
use App\Models\User;
use Livewire\Livewire;

test('un invité ne peut pas accéder aux actualites', function () {
    $this->get(route('actualites.index'))->assertRedirect(route('login'));
});

test('un utilisateur connecté voit la liste des actualites', function () {
    Actualite::factory()->count(3)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('actualites.index'))
        ->assertOk();
});

test('un utilisateur peut créer : Actualite', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::actualites.form')
        ->set('titre', 'Valeur de test')
        ->set('contenu', 'Valeur de test')
        ->set('date', '2026-10-03')
        ->call('save')
        ->assertHasNoErrors();

    expect(Actualite::where('user_id', $user->id)->count())->toBe(1);
});

test('le propriétaire peut ouvrir la modification', function () {
    $record = Actualite::factory()->create();

    $this->actingAs($record->user)
        ->get(route('actualites.edit', $record))
        ->assertOk();
});

test('un autre utilisateur ne peut pas modifier', function () {
    $record = Actualite::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('actualites.edit', $record))
        ->assertForbidden();
});

test('un autre utilisateur ne peut pas supprimer', function () {
    $record = Actualite::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::actualites.show', ['actualite' => $record])
        ->call('delete')
        ->assertForbidden();

    expect(Actualite::find($record->id))->not->toBeNull();
});

test('un admin peut supprimer', function () {
    $record = Actualite::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::actualites.show', ['actualite' => $record])
        ->call('delete');

    expect(Actualite::find($record->id))->toBeNull();
});
