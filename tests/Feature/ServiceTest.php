<?php

use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

test('un invité ne peut pas accéder aux services', function () {
    $this->get(route('services.index'))->assertRedirect(route('login'));
});

test('un utilisateur connecté voit la liste des services', function () {
    Service::factory()->count(3)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('services.index'))
        ->assertOk();
});

test('un utilisateur peut créer : Service', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::services.form')
        ->set('nom', 'Valeur de test')
        ->set('description', 'Valeur de test')
        ->set('icone', 'Valeur de test')
        ->call('save')
        ->assertHasNoErrors();

    expect(Service::where('user_id', $user->id)->count())->toBe(1);
});

test('le propriétaire peut ouvrir la modification', function () {
    $record = Service::factory()->create();

    $this->actingAs($record->user)
        ->get(route('services.edit', $record))
        ->assertOk();
});

test('un autre utilisateur ne peut pas modifier', function () {
    $record = Service::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('services.edit', $record))
        ->assertForbidden();
});

test('un autre utilisateur ne peut pas supprimer', function () {
    $record = Service::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::services.show', ['service' => $record])
        ->call('delete')
        ->assertForbidden();

    expect(Service::find($record->id))->not->toBeNull();
});

test('un admin peut supprimer', function () {
    $record = Service::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::services.show', ['service' => $record])
        ->call('delete');

    expect(Service::find($record->id))->toBeNull();
});
