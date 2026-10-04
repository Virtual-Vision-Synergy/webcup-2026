<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

function role(string $code): Role
{
    return Role::where('code', $code)->firstOrFail();
}

test('les trois rôles de base existent après les migrations', function () {
    expect(Role::orderBy('id')->pluck('code')->all())->toBe(['citoyen', 'agent', 'admin']);
});

test('un nouvel inscrit est citoyen, même s\'il envoie un role_id', function () {
    $this->post(route('register.store'), jetonAntiRobot('inscription') + [
        'name' => 'Pirate',
        'email' => 'pirate@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role_id' => role(Role::ADMIN)->id,
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'pirate@example.com')->firstOrFail();

    expect($user->isCitoyen())->toBeTrue()
        ->and($user->isAdmin())->toBeFalse();
});

test('le rôle est affiché une fois connecté', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Agent municipal');
});

test('un admin peut changer le rôle d\'un compte', function () {
    $citoyen = User::factory()->create();

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(EditUser::class, ['record' => $citoyen->getRouteKey()])
        ->fillForm(['role_id' => role(Role::AGENT)->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($citoyen->refresh()->isAgent())->toBeTrue();
});

test('un citoyen ou un agent qui change un rôle reçoit 403', function (string $state) {
    $acteur = User::factory()->{$state}()->create();
    $cible = User::factory()->create();

    expect(Gate::forUser($acteur)->denies('updateRole', $cible))->toBeTrue();

    $this->actingAs($acteur)
        ->get("/admin/users/{$cible->getRouteKey()}/edit")
        ->assertForbidden();

    expect($cible->refresh()->isCitoyen())->toBeTrue();
})->with(['citoyen' => 'citoyen', 'agent' => 'agent']);

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('roles.index'))->assertRedirect(route('login'));
});

test('on ne peut pas retirer le rôle du dernier administrateur', function () {
    $admin = User::factory()->admin()->create();

    expect(fn () => $admin->changerRole(role(Role::CITOYEN)))->toThrow(DomainException::class);
    expect($admin->refresh()->isAdmin())->toBeTrue();

    User::factory()->admin()->create();
    $admin->changerRole(role(Role::CITOYEN));

    expect($admin->refresh()->isCitoyen())->toBeTrue();
});

test('un admin gère la liste des rôles', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('roles.index'))
        ->assertOk()
        ->assertSee('Agent municipal');

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::roles.form')
        ->set('code', 'elu')
        ->set('label', 'Élu municipal')
        ->call('save')
        ->assertHasNoErrors();

    expect(Role::where('code', 'elu')->exists())->toBeTrue();
});

test('un citoyen ou un agent n\'accède pas à la gestion des rôles', function (string $state) {
    $acteur = User::factory()->{$state}()->create();

    $this->actingAs($acteur)->get(route('roles.index'))->assertForbidden();
    $this->actingAs($acteur)->get(route('roles.edit', role(Role::ADMIN)))->assertForbidden();

    Livewire::actingAs($acteur)->test('pages::roles.form')->assertForbidden();
})->with(['citoyen' => 'citoyen', 'agent' => 'agent']);

test('un rôle de base ne peut pas être supprimé', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::roles.show', ['role' => role(Role::AGENT)])
        ->call('delete')
        ->assertForbidden();

    expect(Role::where('code', Role::AGENT)->exists())->toBeTrue();
});
