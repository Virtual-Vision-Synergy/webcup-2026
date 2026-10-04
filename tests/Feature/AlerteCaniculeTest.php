<?php

use App\Filament\Resources\AlertesCanicule\Pages\ManageAlertesCanicule;
use App\Models\AlerteCanicule;
use App\Models\Quartier;
use App\Models\RecommandationCanicule;
use App\Models\User;
use App\Notifications\AlerteCaniculePubliee;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('une alerte sur le quartier Nord s’affiche en bandeau pour le Nord, pas pour le Sud', function () {
    AlerteCanicule::factory()->pourQuartiers('nord')->create();
    $nord = User::factory()->citoyen()->quartier('nord')->create();
    $sud = User::factory()->citoyen()->quartier('sud')->create();

    $this->actingAs($nord)->get(route('dashboard'))->assertSee('data-test="bandeau-canicule"', false);
    $this->actingAs($sud)->get(route('dashboard'))->assertDontSee('data-test="bandeau-canicule"', false);
});

test('déclencher une alerte depuis Filament notifie uniquement les habitants des quartiers touchés', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $nord = User::factory()->citoyen()->quartier('nord')->create();
    $est = User::factory()->citoyen()->quartier('est')->create();
    $sud = User::factory()->citoyen()->quartier('sud')->create();

    $this->actingAs($admin);

    Livewire::test(ManageAlertesCanicule::class)
        ->callAction('create', data: [
            'quartiers' => [Quartier::idPour('nord'), Quartier::idPour('est')],
            'niveau' => 'urgence',
            'temperature_max' => 41,
            'debut' => now()->subMinute()->toDateTimeString(),
            'fin' => now()->addDay()->toDateTimeString(),
            'message' => 'Chaleur extrême cet après-midi.',
        ])
        ->assertHasNoActionErrors();

    $alerte = AlerteCanicule::query()->sole();

    expect($alerte->user_id)->toBe($admin->id)
        ->and($alerte->notified_at)->not->toBeNull();

    Notification::assertSentTo([$nord, $est], AlerteCaniculePubliee::class);
    Notification::assertNotSentTo($sud, AlerteCaniculePubliee::class);
});

test('une alerte programmée n’est notifiée qu’à son début, une seule fois', function () {
    Notification::fake();
    $nord = User::factory()->citoyen()->quartier('nord')->create();
    $alerte = AlerteCanicule::factory()->programmee()->pourQuartiers('nord')->create();

    $this->artisan('canicule:notify')->assertSuccessful();
    Notification::assertNothingSent();

    $this->travelTo($alerte->debut->addMinute());
    $this->artisan('canicule:notify');
    $this->artisan('canicule:notify');

    Notification::assertSentToTimes($nord, AlerteCaniculePubliee::class, 1);
});

test('le profil « personnes âgées » affiche les conseils écrits pour les personnes âgées', function () {
    AlerteCanicule::factory()->pourQuartiers('nord')->create(['niveau' => 'urgence']);

    Livewire::test('pages::canicule.index')
        ->set('profil', 'personnes_agees')
        ->assertSee(RecommandationCanicule::pour('personnes_agees', 'urgence')[0])
        ->assertDontSee(RecommandationCanicule::pour('enfants', 'urgence')[0]);
});

test('sans aucune clé IA ni réseau, la page canicule fonctionne avec les textes écrits', function () {
    config(['services.openrouter.key' => null]);
    Http::preventStrayRequests();
    AlerteCanicule::factory()->pourQuartiers('sud')->create();

    $this->get(route('canicule'))
        ->assertOk()
        ->assertSee('Alertes en cours')
        ->assertSee(RecommandationCanicule::pour('tout_public', 'alerte')[0])
        ->assertSee('tel:124', false);
});

test('un habitant enregistre son profil sur son propre compte uniquement', function () {
    $habitant = User::factory()->citoyen()->create();
    $autre = User::factory()->citoyen()->create();

    Livewire::actingAs($habitant)
        ->test('pages::canicule.index')
        ->set('profil', 'femmes_enceintes')
        ->call('enregistrerProfil')
        ->assertHasNoErrors();

    expect($habitant->refresh()->profil_canicule)->toBe('femmes_enceintes')
        ->and($autre->refresh()->profil_canicule)->toBeNull();

    Livewire::actingAs($habitant)->test('pages::canicule.index')->set('profil', 'admin')->call('enregistrerProfil')->assertHasErrors('profil');
});

test('un visiteur ne peut pas enregistrer de profil et profil_canicule n’est pas assignable en masse', function () {
    Livewire::test('pages::canicule.index')->call('enregistrerProfil')->assertForbidden();

    expect((new User)->isFillable('profil_canicule'))->toBeFalse()
        ->and((new AlerteCanicule)->isFillable('user_id'))->toBeFalse()
        ->and((new AlerteCanicule)->isFillable('notified_at'))->toBeFalse();
});

test('un citoyen ou un agent ne peut pas gérer les alertes ni les conseils canicule', function () {
    foreach ([User::factory()->citoyen()->create(), User::factory()->agent()->create()] as $user) {
        $this->actingAs($user)->get('/admin/alertes-canicule')->assertForbidden();
        $this->actingAs($user)->get('/admin/conseils-canicule')->assertForbidden();
    }

    $this->actingAs(User::factory()->admin()->create())->get('/admin/alertes-canicule')->assertOk();
});

test('le message d’une alerte contenant du HTML est affiché échappé', function () {
    AlerteCanicule::factory()->pourQuartiers('nord')->create(['message' => '<script>alert(1)</script>']);

    $this->get(route('canicule'))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
});
