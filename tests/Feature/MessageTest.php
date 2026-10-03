<?php

use App\Models\Message;
use App\Models\User;
use Livewire\Livewire;

test('un invité ne peut pas accéder aux messages', function () {
    $this->get(route('messages.index'))->assertRedirect(route('login'));
});

test('un utilisateur connecté voit la liste des messages', function () {
    Message::factory()->count(3)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('messages.index'))
        ->assertOk();
});

test('un utilisateur peut créer : Message', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::messages.form')
        ->set('nom', 'Valeur de test')
        ->set('email', 'Valeur de test')
        ->set('sujet', 'Valeur de test')
        ->set('message', 'Valeur de test')
        ->call('save')
        ->assertHasNoErrors();

    expect(Message::where('user_id', $user->id)->count())->toBe(1);
});

test('le propriétaire peut ouvrir la modification', function () {
    $record = Message::factory()->create();

    $this->actingAs($record->user)
        ->get(route('messages.edit', $record))
        ->assertOk();
});

test('un autre utilisateur ne peut pas modifier', function () {
    $record = Message::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('messages.edit', $record))
        ->assertForbidden();
});

test('un autre utilisateur ne peut pas supprimer', function () {
    $record = Message::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::messages.show', ['message' => $record])
        ->call('delete')
        ->assertForbidden();

    expect(Message::find($record->id))->not->toBeNull();
});

test('un admin peut supprimer', function () {
    $record = Message::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::messages.show', ['message' => $record])
        ->call('delete');

    expect(Message::find($record->id))->toBeNull();
});
