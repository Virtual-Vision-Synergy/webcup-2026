<?php

use App\Models\Annonce;
use App\Models\LigneTransport;
use App\Models\Partner;
use App\Models\Projet;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * D09 : accès par rôle (citoyen / agent / admin) à l'espace agent et aux actions sensibles.
 */
beforeEach(function () {
    Http::fake();
    config()->set('services.novaterra.key', null);
});

dataset('pages agent', [
    'agent.index', 'agent.tableau-de-bord', 'agent.annonces.index', 'agent.annonces.create',
    'agent.citizens.index', 'agent.citizens.create', 'agent.citizens.import', 'agent.demandes',
    'agent.signalements.similaires', 'agent.security.index', 'agent.appointments.index',
    'agent.services.index', 'agent.audit.index', 'agent.concerns.index', 'agent.partners.index',
    'agent.partners.create', 'agent.ideas.index', 'agent.reviews.index',
]);

test('un invité est renvoyé vers la connexion', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with('pages agent');

test('un citoyen reçoit un 403 en français', function (string $route) {
    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route($route))
        ->assertForbidden()
        ->assertSee('Accès refusé');
})->with('pages agent');

test('un agent accède à la page', function (string $route) {
    $this->actingAs(User::factory()->agent()->create())->get(route($route))->assertOk();
})->with('pages agent');

test('toute route de l\'espace agent exige la connexion et le rôle agent ou admin', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => $route->uri() === 'agent' || str_starts_with($route->uri(), 'agent/'));

    expect($routes)->not->toBeEmpty();

    $routes->each(fn ($route) => expect($route->gatherMiddleware())
        ->toContain('auth', 'can:viewAgentSpace'));
});

test('la page 403 n\'affiche aucune donnée utilisateur non échappée', function () {
    $citoyen = User::factory()->citoyen()->create(['name' => '<script>alert(1)</script>']);

    $this->actingAs($citoyen)->get(route('agent.index'))
        ->assertForbidden()
        ->assertSee('Vous n’avez pas les droits nécessaires', false)
        ->assertDontSee('<script>alert(1)</script>', false);

    $this->actingAs($citoyen)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('ni un invité ni un citoyen ne voient de lien vers l\'espace agent', function () {
    $lien = route('agent.tableau-de-bord');

    $this->get(route('home'))->assertOk()->assertDontSee($lien, false);

    $citoyen = User::factory()->citoyen()->create();
    $this->actingAs($citoyen)->get(route('dashboard'))->assertOk()->assertDontSee($lien, false);
    $this->actingAs($citoyen)->get(route('home'))->assertOk()->assertDontSee($lien, false);
});

test('un agent voit le lien vers l\'espace agent', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get(route('dashboard'))
        ->assertSee(route('agent.tableau-de-bord'), false);
});

dataset('suppressions', [
    'partenaire' => fn () => ['pages::partners.form', 'partner', Partner::factory()->create()],
    'projet' => fn () => ['pages::projets.show', 'projet', Projet::factory()->create()],
    'ligne de transport' => fn () => ['pages::transports.show', 'ligneTransport', LigneTransport::factory()->create()],
    'service' => fn () => ['pages::services.show', 'service', Service::factory()->create()],
]);

test('un agent ne peut pas supprimer, un admin le peut', function (array $cas) {
    [$page, $parametre, $element] = $cas;
    $agent = User::factory()->agent()->create();

    if ($element instanceof Service) {
        // Un agent créateur d'un service n'en devient pas pour autant autorisé à le supprimer.
        $element->forceFill(['user_id' => $agent->id])->save();
    }

    expect(Gate::forUser($agent)->denies('delete', $element))->toBeTrue();

    Livewire::actingAs($agent)->test($page, [$parametre => $element])->call('delete')->assertForbidden();
    expect($element->fresh())->not->toBeNull();

    Livewire::actingAs(User::factory()->admin()->create())->test($page, [$parametre => $element])->call('delete');
    expect($element->fresh())->toBeNull();
})->with('suppressions');

test('un agent ne peut pas supprimer un message général, un admin le peut', function () {
    $annonce = Annonce::factory()->active()->create();

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::annonces.index')
        ->call('delete', $annonce->id)
        ->assertForbidden();
    expect($annonce->fresh())->not->toBeNull();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::annonces.index')
        ->call('delete', $annonce->id);
    expect($annonce->fresh())->toBeNull();
});

test('le bouton « Supprimer » d\'un partenaire n\'apparaît que pour un admin', function () {
    $partner = Partner::factory()->create();

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::partners.form', ['partner' => $partner])
        ->assertDontSee('Supprimer ce partenaire ?');

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::partners.form', ['partner' => $partner])
        ->assertSee('Supprimer ce partenaire ?');
});

test('la modification du profil ne permet pas de changer de rôle', function () {
    $citoyen = User::factory()->citoyen()->create();

    $composant = Livewire::actingAs($citoyen)->test('pages::settings.profile');

    expect(fn () => $composant->set('role_id', Role::idFor(Role::ADMIN)))->toThrow(Exception::class);

    $composant->set('name', 'Nouveau nom')->call('updateProfileInformation');

    expect($citoyen->fresh()->isCitoyen())->toBeTrue();
});

test('le dernier administrateur ne peut pas supprimer son compte', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors(['password']);

    expect($admin->fresh())->not->toBeNull();

    User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasNoErrors();

    expect($admin->fresh())->toBeNull();
});
