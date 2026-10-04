<?php

use App\Models\Demarche;
use App\Models\Onboarding;
use App\Models\Quartier;
use App\Models\Service;
use App\Models\User;
use App\Services\OnboardingProgress;
use Livewire\Livewire;

function progression(User $user): OnboardingProgress
{
    return OnboardingProgress::pour($user->fresh());
}

test('un invité est renvoyé vers la connexion depuis le parcours', function () {
    $this->get(route('onboarding.show'))->assertRedirect(route('login'));
});

test('un nouveau citoyen est redirigé vers le parcours après connexion', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), jetonAntiRobot('connexion') + ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('onboarding.show'));
});

test('un nouvel inscrit est redirigé vers le parcours', function () {
    $this->post(route('register.store'), jetonAntiRobot('inscription') + [
        'name' => 'Hery Rakoto',
        'email' => 'hery@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('onboarding.show'));
});

test('un agent ou un admin n\'est jamais redirigé vers le parcours', function (string $role) {
    $user = User::factory()->{$role}()->create();

    $this->post(route('login.store'), jetonAntiRobot('connexion') + ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
})->with(['agent', 'admin']);

test('un agent ou un admin reçoit 403 sur le parcours et sur « Passer »', function (string $role) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('onboarding.show'))->assertForbidden();

    Livewire::actingAs($user)->test('pages::onboarding.index')->assertForbidden();
    expect(Onboarding::count())->toBe(0);
})->with(['agent', 'admin']);

test('un citoyen voit son parcours avec l\'indicateur de progression accessible', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('onboarding.show'))
        ->assertOk()
        ->assertSee('Étape 1 · Compléter mon profil')
        ->assertSee('role="progressbar"', false)
        ->assertSee('aria-valuemin="0"', false)
        ->assertSee('aria-valuemax="3"', false)
        ->assertSee('aria-valuenow="0"', false)
        ->assertSee('En cours')
        ->assertSeeText('Passer pour l\'instant');
});

test('passer le parcours supprime redirection et rappel', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::onboarding.index')
        ->call('passer')
        ->assertRedirect(route('dashboard'));

    expect($user->onboarding()->value('skipped_at'))->not->toBeNull();

    $this->post(route('logout'));
    $this->post(route('login.store'), jetonAntiRobot('connexion') + ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))->assertOk()->assertDontSee('Reprendre la prise en main');
});

test('le rappel s\'affiche sur le tableau de bord tant que le parcours est en cours', function () {
    $this->actingAs(User::factory()->profilComplet()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Reprendre la prise en main — 1/3')
        ->assertSee('aria-valuenow="1"', false);
});

test('la progression suit les vraies actions du citoyen jusqu\'à la fin du parcours', function () {
    $user = User::factory()->create();
    $service = Service::factory()->create();
    expect(progression($user)->nombreFaites())->toBe(0);

    $this->actingAs($user)->get(route('onboarding.show'))->assertOk();

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('telephone', '034 12 345 67')
        ->set('quartier_id', (string) Quartier::idPour('sud'))
        ->call('updateProfileInformation')
        ->assertHasNoErrors()
        ->assertRedirect(route('onboarding.show'));
    expect(progression($user)->nombreFaites())->toBe(1);

    $this->get(route('services.show', $service))->assertOk()->assertSee('Continuer la prise en main');
    expect(progression($user)->nombreFaites())->toBe(2)
        ->and($user->onboarding()->value('service_id'))->toBe($service->id);

    Livewire::actingAs($user)
        ->test('pages::demarches.form')
        ->set('titre', 'Certificat de résidence')
        ->set('description', 'Pour mon nouveau logement.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('demarches.accuse', $user->demarches()->sole()));
    expect(progression($user)->nombreFaites())->toBe(3)
        ->and($user->onboarding()->value('completed_at'))->not->toBeNull();

    $this->get(route('onboarding.show'))->assertOk()->assertSee('vous êtes prêt');

    $this->post(route('logout'));
    $this->post(route('login.store'), jetonAntiRobot('connexion') + ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
});

test('le service consulté est pré-sélectionné pour la démarche', function () {
    $user = User::factory()->create();
    $service = Service::factory()->create();

    $this->actingAs($user)->get(route('services.show', $service));

    $url = progression($user)->etapes()[2]['url'];
    expect($url)->toBe(route('demarches.create', ['service' => $service->id]));

    $this->get($url)->assertOk();
    Livewire::withQueryParams(['service' => $service->id])
        ->actingAs($user)
        ->test('pages::demarches.form')
        ->assertSet('service_id', (string) $service->id);
});

test('consulter une fiche service ne touche pas le parcours d\'un agent ni d\'un parcours passé', function () {
    $agent = User::factory()->agent()->create();
    $passe = Onboarding::factory()->passe()->create()->user;
    $service = Service::factory()->create();

    $this->actingAs($agent)->get(route('services.show', $service))->assertOk();
    $this->actingAs($passe)->get(route('services.show', $service))->assertOk();

    expect($agent->onboarding()->exists())->toBeFalse()
        ->and($passe->onboarding()->value('service_visited_at'))->toBeNull();
});

test('les champs du parcours ne peuvent pas être envoyés par le citoyen', function () {
    $user = User::factory()->create();
    $autre = User::factory()->create();

    expect((new Onboarding)->isFillable('completed_at'))->toBeFalse()
        ->and((new Onboarding)->isFillable('skipped_at'))->toBeFalse()
        ->and((new Onboarding)->isFillable('user_id'))->toBeFalse();

    // « Passer » agit toujours sur l'utilisateur connecté, quel que soit le paramètre envoyé.
    Livewire::actingAs($user)
        ->test('pages::onboarding.index', ['user_id' => $autre->id])
        ->call('passer');

    expect($user->onboarding()->value('skipped_at'))->not->toBeNull()
        ->and($autre->onboarding()->exists())->toBeFalse();
});

test('à l\'inscription, les champs réservés envoyés sont ignorés', function () {
    $this->post(route('register.store'), jetonAntiRobot('inscription') + [
        'name' => 'Hery Rakoto',
        'email' => 'hery@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role_id' => 3,
        'skipped_at' => now()->toDateTimeString(),
        'completed_at' => now()->toDateTimeString(),
    ])->assertRedirect(route('onboarding.show'));

    $user = User::where('email', 'hery@example.com')->firstOrFail();
    expect($user->isCitoyen())->toBeTrue()
        ->and($user->onboarding()->exists())->toBeFalse();
});

test('un citoyen ayant déjà une démarche et un profil complet n\'a plus que l\'étape 2', function () {
    $user = User::factory()->profilComplet()->create();
    Demarche::factory()->for($user)->create();

    expect(progression($user)->nombreFaites())->toBe(2)
        ->and(progression($user)->etapeCourante())->toBe(2);
});

test('le téléphone du profil est validé', function () {
    Livewire::actingAs($user = User::factory()->create())
        ->test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('telephone', '<script>')
        ->call('updateProfileInformation')
        ->assertHasErrors(['telephone' => 'regex']);
});
