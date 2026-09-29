<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Livewire\Livewire;

test('un admin peut promouvoir un autre utilisateur', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['role' => 'admin'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->role)->toBe('admin');
});

test('un admin ne peut pas modifier son propre rôle', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
        ->fillForm(['name' => 'Nouveau nom'])
        ->call('save');

    expect($admin->refresh()->role)->toBe('admin')
        ->and($admin->name)->toBe('Nouveau nom');
});

test('un admin peut créer un utilisateur avec un rôle', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Jury',
            'email' => 'jury@example.com',
            'password' => 'MotDePasse123!',
            'role' => 'user',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'jury@example.com')->value('role'))->toBe('user');
});

test('un utilisateur normal ne peut pas ouvrir la gestion des utilisateurs', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/users')
        ->assertForbidden();
});
