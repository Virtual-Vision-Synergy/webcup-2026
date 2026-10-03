<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('un invité est redirigé vers la connexion', function () {
    $citoyen = User::factory()->citoyen()->create();

    $this->get(route('agent.citizens.index'))->assertRedirect(route('login'));
    $this->get(route('agent.citizens.show', $citoyen))->assertRedirect(route('login'));
});

test('un citoyen reçoit un 403 sur la liste, la fiche et les actions', function () {
    $citoyen = User::factory()->citoyen()->create();
    $autre = User::factory()->citoyen()->create();
    $desactive = User::factory()->citoyen()->deactivated()->create();

    $this->actingAs($citoyen)->get(route('agent.citizens.index'))->assertForbidden();
    $this->actingAs($citoyen)->get(route('agent.citizens.show', $autre))->assertForbidden();

    Livewire::actingAs($citoyen)->test('pages::agent.citizens.show', ['user' => $autre])->assertForbidden();
    expect($citoyen->can('deactivate', $autre))->toBeFalse()
        ->and($citoyen->can('reactivate', $desactive))->toBeFalse();
});

test('un agent voit la liste des citoyens, sans les agents ni les admins', function () {
    $agent = User::factory()->agent()->create(['name' => 'Agent Zoé']);
    User::factory()->admin()->create(['name' => 'Admin Paul']);
    User::factory()->agent()->create(['name' => 'Agent Marc']);
    User::factory()->citoyen()->create(['name' => 'Hanitra Rakoto']);

    $this->actingAs($agent)->get(route('agent.citizens.index'))
        ->assertOk()
        ->assertSee('Hanitra Rakoto')
        ->assertDontSee('Admin Paul')
        ->assertDontSee('Agent Marc');
});

test('la recherche par nom et par e-mail filtre la liste', function () {
    $agent = User::factory()->agent()->create();
    User::factory()->citoyen()->create(['name' => 'Hanitra Rakoto', 'email' => 'hanitra@example.com']);
    User::factory()->citoyen()->create(['name' => 'Lucas Moreau', 'email' => 'lucas@example.com']);

    $this->actingAs($agent)->get(route('agent.citizens.index', ['q' => 'Hanitra']))
        ->assertSee('Hanitra Rakoto')
        ->assertDontSee('Lucas Moreau');

    Livewire::actingAs($agent)->test('pages::agent.citizens.index')
        ->set('search', 'lucas@')
        ->assertSee('Lucas Moreau')
        ->assertDontSee('Hanitra Rakoto')
        ->set('search', '%')
        ->assertDontSee('Lucas Moreau');
});

test('le filtre par statut ne montre que les comptes désactivés', function () {
    $agent = User::factory()->agent()->create();
    User::factory()->citoyen()->create(['name' => 'Compte Actif']);
    User::factory()->citoyen()->deactivated()->create(['name' => 'Compte Bloqué']);

    Livewire::actingAs($agent)->test('pages::agent.citizens.index')
        ->set('statut', 'desactive')
        ->assertSee('Compte Bloqué')
        ->assertDontSee('Compte Actif');
});

test('un agent peut désactiver puis réactiver un citoyen', function () {
    $agent = User::factory()->agent()->create();
    $citoyen = User::factory()->citoyen()->create();

    $page = Livewire::actingAs($agent)->test('pages::agent.citizens.show', ['user' => $citoyen])
        ->assertSee('Désactiver le compte')
        ->call('deactivate')
        ->assertHasNoErrors();

    expect($citoyen->fresh()->isActive())->toBeFalse();

    $page->call('reactivate')
        ->assertSee(['Compte désactivé', 'Compte réactivé', 'par '.$agent->name]);

    expect($citoyen->fresh()->isActive())->toBeTrue()
        ->and(AuditLog::where('actor_id', $agent->id)->where('subject_id', $citoyen->id)->pluck('action')->all())
        ->toEqualCanonicalizing(['deactivated', 'reactivated']);
});

test('un agent reçoit un 403 sur la fiche d\'un admin ou d\'un autre agent', function (string $role) {
    $agent = User::factory()->agent()->create();
    $cible = User::factory()->{$role}()->create();

    $this->actingAs($agent)->get(route('agent.citizens.show', $cible))->assertForbidden();
    Livewire::actingAs($agent)->test('pages::agent.citizens.show', ['user' => $cible])->assertForbidden();

    expect($agent->can('deactivate', $cible))->toBeFalse()
        ->and($cible->fresh()->isActive())->toBeTrue();
})->with(['admin', 'agent']);

test('un admin peut désactiver un agent mais pas un autre admin', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();
    $autreAdmin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test('pages::agent.citizens.show', ['user' => $agent])->call('deactivate');

    expect($agent->fresh()->isActive())->toBeFalse()
        ->and($admin->can('viewAccount', $autreAdmin))->toBeFalse()
        ->and($admin->can('deactivate', $autreAdmin))->toBeFalse();
});

test('un champ réservé envoyé par le navigateur ne modifie rien', function () {
    $agent = User::factory()->agent()->create();
    $citoyen = User::factory()->citoyen()->create();

    expect(fn () => Livewire::actingAs($agent)->test('pages::agent.citizens.show', ['user' => $citoyen])
        ->set('account.role_id', Role::idFor(Role::ADMIN)))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    $citoyen->fill(['role_id' => Role::idFor(Role::ADMIN), 'deactivated_at' => now()])->save();

    expect($citoyen->fresh()->isCitoyen())->toBeTrue()
        ->and($citoyen->fresh()->isActive())->toBeTrue();
});

test('personne ne peut désactiver son propre compte', function (string $role) {
    $user = User::factory()->{$role}()->create();

    expect($user->can('deactivate', $user))->toBeFalse();
    $this->actingAs($user)->get(route('agent.citizens.show', $user))->assertForbidden();
})->with(['admin', 'agent']);

test('un citoyen désactivé ne peut plus se connecter : message en français', function () {
    $citoyen = User::factory()->citoyen()->deactivated()->create();

    $this->post(route('login.store'), ['email' => $citoyen->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => EnsureAccountIsActive::MESSAGE]);

    $this->assertGuest();
});

test('un citoyen désactivé avec un mauvais mot de passe reçoit le message générique', function () {
    app()->setLocale('fr');
    $citoyen = User::factory()->citoyen()->deactivated()->create();

    $this->post(route('login.store'), ['email' => $citoyen->email, 'password' => 'mauvais'])
        ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent à aucun compte.']);

    $this->assertGuest();
});

test('un citoyen connecté puis désactivé est déconnecté à la requête suivante', function () {
    $citoyen = User::factory()->citoyen()->create();

    $this->actingAs($citoyen)->get(route('dashboard'))->assertOk();

    $citoyen->deactivate();

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => EnsureAccountIsActive::MESSAGE]);

    $this->assertGuest();
});

test('un citoyen réactivé peut de nouveau se connecter', function () {
    $citoyen = User::factory()->citoyen()->deactivated()->create();

    $citoyen->reactivate();

    $this->post(route('login.store'), ['email' => $citoyen->email, 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($citoyen);
});

test('un compte désactivé ne peut pas réinitialiser son mot de passe', function () {
    Notification::fake();
    $citoyen = User::factory()->citoyen()->deactivated()->create();

    $this->post(route('password.request'), ['email' => $citoyen->email]);

    Notification::assertSentTo($citoyen, ResetPassword::class, function (ResetPassword $notification) use ($citoyen) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $citoyen->email,
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertSessionHasErrors(['email' => EnsureAccountIsActive::MESSAGE]);

        return true;
    });
});
