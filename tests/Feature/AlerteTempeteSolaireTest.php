<?php

use App\Models\Annonce;
use App\Models\User;
use App\Notifications\ImportantAnnouncementPublished;
use App\Support\InfosEssentielles;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * F104 — Alerte « Tempête solaire » publiée en 1 clic sur toute la ville (bandeau, notification, infos essentielles).
 */
beforeEach(function () {
    Storage::fake('local');
    // 3 octobre 2026 à 10 h UTC = 13 h, heure de Madagascar.
    $this->travelTo(now()->setDateTime(2026, 10, 3, 10, 0));
});

test('un agent publie l’alerte tempête solaire en 1 clic : toute la ville, consignes et heures estimées', function () {
    Notification::fake();
    $agent = User::factory()->agent()->create();

    Livewire::actingAs($agent)->test('pages::annonces.index')
        ->call('publierModele', 'tempete-solaire')
        ->assertHasNoErrors();

    $alerte = Annonce::sole();

    expect($alerte->titre)->toBe(Annonce::MODELES['tempete-solaire']['titre'])
        ->and($alerte->niveau)->toBe('danger')
        ->and($alerte->quartier_id)->toBeNull()
        ->and($alerte->user_id)->toBe($agent->id)
        ->and($alerte->statut())->toBe('en_cours')
        ->and($alerte->impact_prevu_le->equalTo(now()->addMinutes(20)))->toBeTrue()
        ->and($alerte->fin->equalTo(now()->addHours(6)))->toBeTrue()
        ->and($alerte->listeConsignes())->toHaveCount(6)
        ->and($alerte->consignes)->toContain('117')->toContain('GPS')->toContain('enregistrez vos démarches');
});

test('l’alerte publiée est visible par tous en bandeau, avec le début estimé et la fin de l’alerte', function () {
    Notification::fake();
    Livewire::actingAs(User::factory()->agent()->create())->test('pages::annonces.index')
        ->call('publierModele', 'tempete-solaire');
    auth()->logout();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Tempête solaire — communications perturbées')
        ->assertSee('data-test="compte-a-rebours"', false)
        ->assertSee('Début estimé de la perturbation')
        ->assertSee('samedi 3 octobre à 13 h 20')
        ->assertSee('Fin de l’alerte')
        ->assertSee('samedi 3 octobre à 19 h 00');

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Tempête solaire — communications perturbées');
});

test('la publication notifie tous les habitants (cloche et e-mail), pas les agents', function () {
    Notification::fake();
    $citoyens = User::factory(2)->citoyen()->create(['notifier_par_email' => true]);
    $agent = User::factory()->agent()->create();

    Livewire::actingAs($agent)->test('pages::annonces.index')->call('publierModele', 'tempete-solaire');

    Notification::assertSentTo(
        $citoyens,
        ImportantAnnouncementPublished::class,
        fn (ImportantAnnouncementPublished $notification, array $canaux): bool => in_array('database', $canaux, true)
            && in_array('mail', $canaux, true)
            && str_contains($notification->periode(), 'Perturbation estimée à partir de samedi 3 octobre à 13 h 20'),
    );
    Notification::assertNotSentTo($agent, ImportantAnnouncementPublished::class);
    expect(Annonce::sole()->notified_at)->not->toBeNull();
});

test('les consignes et les numéros d’urgence sont repris dans la page « Infos essentielles »', function () {
    Notification::fake();
    $this->withoutDefer();

    Livewire::actingAs(User::factory()->agent()->create())->test('pages::annonces.index')
        ->call('publierModele', 'tempete-solaire');
    auth()->logout();

    $response = $this->get(route('infos-essentielles'))
        ->assertOk()
        ->assertSee('data-test="alerte-en-cours"', false)
        ->assertSee('Tempête solaire — communications perturbées')
        ->assertSee('Début estimé de la perturbation')
        ->assertSee('Notez sur papier les numéros d’urgence')
        ->assertSee('tel:117', false);

    expect($response->getContent())->not->toContain('<script');
    expect(Storage::disk('local')->get(InfosEssentielles::FICHIER))->toContain('Tempête solaire');
});

test('republier l’alerte remplace celle en cours au lieu de la doubler', function () {
    Notification::fake();
    $test = Livewire::actingAs(User::factory()->agent()->create())->test('pages::annonces.index');

    $test->call('publierModele', 'tempete-solaire');
    $this->travel(5)->minutes();
    $test->call('publierModele', 'tempete-solaire')->assertHasNoErrors();

    expect(Annonce::count())->toBe(2)
        ->and(Annonce::query()->active()->count())->toBe(1);
});

test('un modèle inconnu ne publie rien', function () {
    Livewire::actingAs(User::factory()->agent()->create())->test('pages::annonces.index')
        ->call('publierModele', 'inconnu');

    expect(Annonce::count())->toBe(0);
});

test('un citoyen ne peut pas publier l’alerte tempête solaire (403)', function () {
    $citoyen = User::factory()->citoyen()->create();

    Livewire::actingAs($citoyen)->test('pages::annonces.index')->assertForbidden();
    $this->actingAs($citoyen)->get(route('agent.annonces.index'))->assertForbidden();

    expect(Annonce::count())->toBe(0);
});

test('un agent peut saisir le début estimé de la perturbation dans le formulaire (heure de Madagascar)', function () {
    Notification::fake();

    Livewire::actingAs(User::factory()->agent()->create())->test('pages::annonces.form')
        ->call('appliquerModele', 'tempete-solaire')
        ->assertSet('impact_prevu_le', '2026-10-03T13:20')
        ->call('save')
        ->assertHasNoErrors();

    expect(Annonce::sole()->impact_prevu_le->toDateTimeString())->toBe('2026-10-03 10:20:00');
});
