<?php

use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

test('un invité voit la liste des services', function () {
    Service::factory()->create(['nom' => 'État civil']);
    Service::factory()->create(['nom' => 'Médiathèque Ravinala']);

    $this->get(route('services.index'))
        ->assertOk()
        ->assertSee('État civil')
        ->assertSee('Médiathèque Ravinala');
});

test('la fiche d\'un service affiche la description, les horaires et le contact', function () {
    $service = Service::factory()->create([
        'nom' => 'État civil',
        'description' => 'Actes de naissance et de mariage.',
        'horaires' => 'Lundi au vendredi : 8 h – 16 h',
        'telephone' => '+261 20 22 401 10',
        'email' => 'etat-civil@mairie-novaterra.mg',
    ]);

    expect($service->slug)->toBe('etat-civil');

    $this->get('/services/etat-civil')
        ->assertOk()
        ->assertSee('Actes de naissance et de mariage.')
        ->assertSee('Lundi au vendredi : 8 h – 16 h')
        ->assertSee('+261 20 22 401 10')
        ->assertSee('etat-civil@mairie-novaterra.mg');
});

test('un service inexistant affiche une page 404 en français', function () {
    $this->get('/services/service-inexistant')
        ->assertNotFound()
        ->assertSee('Page introuvable')
        ->assertSee('Retour à la liste des services');
});

test('le seeder crée au moins 6 services', function () {
    $this->seed();

    expect(Service::count())->toBeGreaterThanOrEqual(6);
    $this->get('/services/etat-civil')->assertOk();
});

test('un invité ne voit ni les liens de modification ni l\'auteur', function () {
    $auteur = User::factory()->admin()->create(['name' => 'Auteur Confidentiel']);
    $record = Service::factory()->for($auteur)->create();

    $this->get(route('services.index'))
        ->assertOk()
        ->assertDontSee(route('services.create'))
        ->assertDontSee(route('services.edit', $record))
        ->assertDontSee('Auteur Confidentiel');

    $this->get(route('services.show', $record))
        ->assertOk()
        ->assertDontSee(route('services.edit', $record))
        ->assertDontSee('Auteur Confidentiel');
});

test('un invité est redirigé vers la connexion sur le formulaire', function () {
    $record = Service::factory()->create();

    $this->get(route('services.create'))->assertRedirect(route('login'));
    $this->get(route('services.edit', $record))->assertRedirect(route('login'));
});

test('un invité ne peut ni créer, ni modifier, ni supprimer', function () {
    $record = Service::factory()->create();

    expect(Gate::allows('create', Service::class))->toBeFalse()
        ->and(Gate::allows('update', $record))->toBeFalse()
        ->and(Gate::allows('delete', $record))->toBeFalse();

    Livewire::test('pages::services.index')->call('delete', $record->id)->assertForbidden();

    expect(Service::find($record->id))->not->toBeNull();
});

test('un admin peut créer un service avec un slug unique', function () {
    $admin = User::factory()->admin()->create();
    Service::factory()->create(['nom' => 'Urbanisme']);

    Livewire::actingAs($admin)
        ->test('pages::services.form')
        ->set('nom', 'Urbanisme')
        ->set('description', 'Permis de construire.')
        ->set('telephone', '+261 20 22 401 25')
        ->call('save')
        ->assertHasNoErrors();

    $service = Service::where('user_id', $admin->id)->sole();

    expect($service->slug)->toBe('urbanisme-2');
});

test('le nom et la description sont obligatoires', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::services.form')
        ->set('telephone', '+261 20 22 401 25')
        ->call('save')
        ->assertHasErrors(['nom' => 'required', 'description' => 'required']);
});

test('il faut au moins les horaires ou un moyen de contact', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::services.form')
        ->set('nom', 'Urbanisme')
        ->set('description', 'Permis de construire.')
        ->call('save')
        ->assertHasErrors(['horaires' => 'required_without_all'])
        ->assertSee('Renseignez au moins les horaires ou un moyen de contact');

    expect(Service::count())->toBe(0);
});

test('les horaires seuls suffisent, un e-mail invalide est refusé', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::services.form')
        ->set('nom', 'Urbanisme')
        ->set('description', 'Permis de construire.')
        ->set('email', 'pas-un-email')
        ->call('save')
        ->assertHasErrors(['email' => 'email']);

    Livewire::actingAs($admin)
        ->test('pages::services.form')
        ->set('nom', 'Urbanisme')
        ->set('description', 'Permis de construire.')
        ->set('horaires', 'Lundi : 8 h – 12 h')
        ->call('save')
        ->assertHasNoErrors();

    expect(Service::count())->toBe(1);
});

test('un admin peut ouvrir la modification', function () {
    $record = Service::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('services.edit', $record))
        ->assertOk();
});

test('un utilisateur non admin ne peut ni créer ni modifier', function () {
    $record = Service::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('services.create'))->assertForbidden();
    $this->actingAs($user)->get(route('services.edit', $record))->assertForbidden();
});

test('un utilisateur non admin ne peut pas supprimer', function () {
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
