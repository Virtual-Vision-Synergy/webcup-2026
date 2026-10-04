<?php

use App\Filament\Resources\TentativesBloquees\Pages\ListTentativesBloquees;
use App\Filament\Widgets\ProtectionRobotsOverview;
use App\Models\LoginAttempt;
use App\Models\Message;
use App\Models\Signalement;
use App\Models\TentativeBloquee;
use App\Models\User;
use App\Services\ProtectionFormulaires;
use Livewire\Livewire;

/**
 * F81 : protection des formulaires contre les robots (champ piège, délai minimal, limites de débit, journal admin).
 */
function inscription(array $champs = []): array
{
    return $champs + [
        'name' => 'Voahangy Rasoa',
        'email' => 'voahangy'.fake()->unique()->numberBetween(1, 99999).'@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];
}

function formulaireContact(User $user): mixed
{
    return Livewire::actingAs($user)
        ->test('pages::messages.form')
        ->set('sujet', 'Éclairage public')
        ->set('message', 'Le lampadaire de ma rue ne fonctionne plus.');
}

function formulaireSignalement(User $user): mixed
{
    return Livewire::actingAs($user)
        ->test('pages::signalements.form')
        ->set('categorie', 'voirie')
        ->set('description', 'Nid-de-poule profond devant l\'école.')
        ->set('lieu', 'Rue des Lumières');
}

// --- Inscription (formulaire public) ---

test('inscription : un envoi humain normal crée le compte', function () {
    $this->post(route('register.store'), jetonAntiRobot('inscription') + inscription(['email' => 'humain@example.com']))
        ->assertSessionHasNoErrors();

    $this->assertAuthenticated();
    expect(TentativeBloquee::count())->toBe(0);
});

test('inscription : le champ piège rempli est rejeté et journalisé', function () {
    $this->post(route('register.store'), inscription(['email' => 'robot@example.com']) + [
        ProtectionFormulaires::CHAMP_PIEGE => 'https://spam.example',
    ] + jetonAntiRobot('inscription'))
        ->assertSessionHasErrors('formulaire');

    $this->assertGuest();
    expect(User::where('email', 'robot@example.com')->exists())->toBeFalse()
        ->and(TentativeBloquee::where('formulaire', 'inscription')->where('motif', TentativeBloquee::MOTIF_HONEYPOT)->count())->toBe(1);
});

test('inscription : un envoi direct sans passer par le formulaire est rejeté', function () {
    $this->post(route('register.store'), inscription(['email' => 'curl@example.com']))
        ->assertSessionHasErrors('formulaire');

    expect(User::where('email', 'curl@example.com')->exists())->toBeFalse()
        ->and(TentativeBloquee::where('motif', TentativeBloquee::MOTIF_JETON_INVALIDE)->count())->toBe(1);
});

test('inscription : un jeton d\'un autre formulaire est refusé', function () {
    $this->post(route('register.store'), jetonAntiRobot('connexion') + inscription())
        ->assertSessionHasErrors('formulaire');

    $this->assertGuest();
});

test('inscription : un envoi en moins de 3 secondes est rejeté', function () {
    $jeton = app(ProtectionFormulaires::class)->jeton('inscription');

    $this->post(route('register.store'), inscription() + [ProtectionFormulaires::CHAMP_JETON => $jeton])
        ->assertSessionHasErrors(['formulaire' => 'Envoi trop rapide. Vérifiez vos informations, puis renvoyez le formulaire.']);

    expect(TentativeBloquee::where('motif', TentativeBloquee::MOTIF_TROP_RAPIDE)->count())->toBe(1);
});

test('inscription : au-delà de 5 envois par minute, réponse 429 avec le délai, journalisée', function () {
    foreach (range(1, 5) as $i) {
        $this->post(route('register.store'), jetonAntiRobot('inscription') + inscription());
        auth()->logout();
    }

    $this->post(route('register.store'), jetonAntiRobot('inscription') + inscription())
        ->assertStatus(429)
        ->assertSee('Trop de tentatives, réessayez dans 1 minute.');

    expect(TentativeBloquee::where('formulaire', 'inscription')->where('motif', TentativeBloquee::MOTIF_DEBIT)->count())->toBe(1);
});

test('la page d\'inscription contient le champ piège et le jeton', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('name="'.ProtectionFormulaires::CHAMP_PIEGE.'"', false)
        ->assertSee('name="'.ProtectionFormulaires::CHAMP_JETON.'"', false);
});

// --- Connexion (formulaire public, débit géré par F37) ---

test('connexion : un envoi humain normal connecte l\'utilisateur', function () {
    $user = User::factory()->citoyen()->create();

    $this->post(route('login.store'), jetonAntiRobot('connexion') + ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
});

test('connexion : le champ piège rempli est rejeté, même avec le bon mot de passe', function () {
    $user = User::factory()->citoyen()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', ProtectionFormulaires::CHAMP_PIEGE => 'x'] + jetonAntiRobot('connexion'))
        ->assertSessionHasErrors('formulaire')
        ->assertSessionHasInput('email', $user->email)
        ->assertSessionMissing('_old_input.password');

    $this->assertGuest();
    expect(TentativeBloquee::where('formulaire', 'connexion')->count())->toBe(1);
});

// --- Contact D04 (Livewire, connecté) ---

test('contact : un envoi humain normal est enregistré', function () {
    $user = User::factory()->citoyen()->create();
    $formulaire = formulaireContact($user);
    $this->travel(5)->seconds();

    $formulaire->call('save')->assertHasNoErrors();

    expect(Message::where('user_id', $user->id)->count())->toBe(1);
});

test('contact : le champ piège rempli est rejeté et journalisé', function () {
    $user = User::factory()->citoyen()->create();
    $formulaire = formulaireContact($user)->set('site_web', 'https://spam.example');
    $this->travel(5)->seconds();

    $formulaire->call('save')->assertHasErrors('throttle');

    expect(Message::count())->toBe(0)
        ->and(TentativeBloquee::where('formulaire', 'contact')->where('motif', TentativeBloquee::MOTIF_HONEYPOT)->where('user_id', $user->id)->count())->toBe(1);
});

test('contact : un envoi immédiat est rejeté', function () {
    $user = User::factory()->citoyen()->create();

    formulaireContact($user)->call('save')->assertHasErrors('throttle');

    expect(Message::count())->toBe(0);
});

test('contact : le jeton anti-robot ne peut pas être modifié par le navigateur', function () {
    $user = User::factory()->citoyen()->create();

    expect(fn () => formulaireContact($user)->set('jetonAntiRobot', 'falsifie'))->toThrow(Exception::class);
});

test('contact : au-delà de 5 envois par minute, message « Trop de tentatives »', function () {
    $user = User::factory()->citoyen()->create();
    $formulaire = formulaireContact($user);
    $this->travel(5)->seconds();

    foreach (range(1, 5) as $i) {
        $formulaire->set('sujet', 'Sujet '.$i)->set('message', 'Message '.$i)->call('save')->assertHasNoErrors();
    }

    $formulaire->set('sujet', 'Sujet 6')->set('message', 'Message 6')->call('save')
        ->assertHasErrors(['throttle' => 'Trop de tentatives, réessayez dans 1 minute.']);

    expect(Message::count())->toBe(5)
        ->and(TentativeBloquee::where('motif', TentativeBloquee::MOTIF_DEBIT)->count())->toBe(1);
});

// --- Signalement F25 (Livewire, connecté) ---

test('signalement : un envoi humain normal est enregistré', function () {
    $user = User::factory()->citoyen()->create();
    $formulaire = formulaireSignalement($user);
    $this->travel(5)->seconds();

    $formulaire->call('save')->assertHasNoErrors();

    expect(Signalement::where('user_id', $user->id)->count())->toBe(1);
});

test('signalement : le champ piège rempli est rejeté', function () {
    $user = User::factory()->citoyen()->create();
    $formulaire = formulaireSignalement($user)->set('site_web', 'robot');
    $this->travel(5)->seconds();

    $formulaire->call('save')->assertHasErrors('throttle');

    expect(Signalement::count())->toBe(0)
        ->and(TentativeBloquee::where('formulaire', 'signalement')->count())->toBe(1);
});

test('signalement : au-delà de 10 envois par minute, l\'envoi est bloqué', function () {
    $user = User::factory()->citoyen()->create();
    $formulaire = formulaireSignalement($user);
    $this->travel(5)->seconds();

    foreach (range(1, 10) as $i) {
        $formulaire->set('categorie', 'voirie')->set('description', 'Problème n° '.$i)->set('lieu', 'Rue '.$i)->call('save')->assertHasNoErrors();
    }

    $formulaire->set('categorie', 'voirie')->set('description', 'Problème n° 11')->set('lieu', 'Rue 11')->call('save')
        ->assertHasErrors('throttle');

    expect(Signalement::count())->toBe(10);
});

// --- Visibilité admin (Filament) ---

test('l\'admin voit la liste des envois bloqués et le compteur', function () {
    TentativeBloquee::factory()->count(3)->create(['motif' => TentativeBloquee::MOTIF_HONEYPOT, 'created_at' => now()->subHour()]);
    LoginAttempt::factory()->lockedOut()->create(['created_at' => now()->subHour()]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/robots-bloques')->assertOk()->assertSee('Robots bloqués');

    Livewire::actingAs($admin)
        ->test(ListTentativesBloquees::class)
        ->assertCanSeeTableRecords(TentativeBloquee::all());

    Livewire::actingAs($admin)
        ->test(ProtectionRobotsOverview::class)
        ->assertSee('Envois bloqués')
        ->assertSee('Connexions bloquées');
});

test('un citoyen ou un agent ne peut pas consulter les envois bloqués', function (string $etat) {
    $this->actingAs(User::factory()->{$etat}()->create())
        ->get('/admin/robots-bloques')
        ->assertForbidden();
})->with(['citoyen', 'agent']);

test('le journal ne peut pas être rempli par assignation de masse', function () {
    expect((new TentativeBloquee)->getFillable())->toBe([])
        ->and((new TentativeBloquee)->isFillable('user_id'))->toBeFalse();
});
