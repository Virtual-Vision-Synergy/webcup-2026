<?php

use App\Models\Annonce;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/**
 * D18 — Message général diffusé à tous les habitants.
 */
function remplirAnnonce(mixed $test, array $valeurs = []): mixed
{
    $valeurs += [
        'titre' => 'Coupure d’eau à Ambohijanahary',
        'contenu' => 'L’eau sera coupée de 9 h à 16 h.',
        'niveau' => 'vigilance',
        'debut' => '2026-10-03T09:00',
        'fin' => '2026-10-04T09:00',
    ];

    foreach ($valeurs as $champ => $valeur) {
        $test->set($champ, $valeur);
    }

    return $test;
}

test('un invité est redirigé vers la connexion sur les pages de gestion', function () {
    $annonce = Annonce::factory()->create();

    $this->get(route('agent.annonces.index'))->assertRedirect(route('login'));
    $this->get(route('agent.annonces.create'))->assertRedirect(route('login'));
    $this->get(route('agent.annonces.edit', $annonce))->assertRedirect(route('login'));
});

test('un citoyen reçoit un 403 sur les pages de gestion', function () {
    $annonce = Annonce::factory()->create();
    $citoyen = User::factory()->citoyen()->create();

    $this->actingAs($citoyen)->get(route('agent.annonces.index'))->assertForbidden();
    $this->actingAs($citoyen)->get(route('agent.annonces.create'))->assertForbidden();
    $this->actingAs($citoyen)->get(route('agent.annonces.edit', $annonce))->assertForbidden();
});

test('un citoyen ne peut ni créer, ni modifier, ni supprimer, ni dépublier un message', function () {
    $annonce = Annonce::factory()->create(['titre' => 'Titre original']);
    $citoyen = User::factory()->citoyen()->create();

    Livewire::actingAs($citoyen)->test('pages::annonces.form')->assertForbidden();
    Livewire::actingAs($citoyen)->test('pages::annonces.form', ['annonce' => $annonce])->assertForbidden();
    Livewire::actingAs($citoyen)->test('pages::annonces.index')->assertForbidden();

    expect(Annonce::count())->toBe(1)
        ->and($annonce->fresh()->titre)->toBe('Titre original');
});

test('un agent peut publier un message général', function () {
    $agent = User::factory()->agent()->create();

    remplirAnnonce(Livewire::actingAs($agent)->test('pages::annonces.form'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('agent.annonces.index'));

    $annonce = Annonce::sole();
    expect($annonce->user_id)->toBe($agent->id)
        ->and($annonce->niveau)->toBe('vigilance')
        // Saisie en heure de Madagascar (UTC+3), stockée en UTC.
        ->and($annonce->debut->format('Y-m-d H:i'))->toBe('2026-10-03 06:00');
});

test('le formulaire de création est pré-rempli de maintenant à +24 h', function () {
    $this->travelTo(now()->setDateTime(2026, 10, 3, 10, 0));

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::annonces.form')
        ->assertSet('debut', '2026-10-03T13:00')
        ->assertSet('fin', '2026-10-04T13:00');
});

test('un admin peut modifier, dépublier et supprimer un message', function () {
    $admin = User::factory()->admin()->create();
    $annonce = Annonce::factory()->active()->create();

    remplirAnnonce(Livewire::actingAs($admin)->test('pages::annonces.form', ['annonce' => $annonce]), ['titre' => 'Titre corrigé'])
        ->call('save')
        ->assertHasNoErrors();
    expect($annonce->fresh()->titre)->toBe('Titre corrigé');

    $annonce->update(['debut' => now()->subHour(), 'fin' => now()->addDay()]);
    Livewire::actingAs($admin)->test('pages::annonces.index')->call('depublier', $annonce->id);
    expect($annonce->fresh()->statut())->toBe('expire');

    Livewire::actingAs($admin)->test('pages::annonces.index')->call('delete', $annonce->id);
    expect(Annonce::find($annonce->id))->toBeNull();
});

test('l’auteur est toujours l’utilisateur connecté, jamais une valeur envoyée par le client', function () {
    $agent = User::factory()->agent()->create();
    $autre = User::factory()->admin()->create();

    // user_id n'est pas une propriété du composant : Livewire refuse de la définir.
    expect(fn () => Livewire::actingAs($agent)->test('pages::annonces.form')->set('user_id', $autre->id))
        ->toThrow(Exception::class);

    remplirAnnonce(Livewire::actingAs($agent)->test('pages::annonces.form'))->call('save');

    expect(Annonce::sole()->user_id)->toBe($agent->id)
        ->and((new Annonce(['user_id' => $autre->id]))->user_id)->toBeNull();
});

test('la validation refuse les champs manquants, une fin avant le début et un niveau inconnu', function () {
    $agent = User::factory()->agent()->create();

    remplirAnnonce(Livewire::actingAs($agent)->test('pages::annonces.form'), ['titre' => '', 'contenu' => '', 'debut' => '', 'fin' => ''])
        ->call('save')
        ->assertHasErrors(['titre' => 'required', 'contenu' => 'required', 'debut' => 'required', 'fin' => 'required']);

    remplirAnnonce(Livewire::actingAs($agent)->test('pages::annonces.form'), ['debut' => '2026-10-04T09:00', 'fin' => '2026-10-03T09:00'])
        ->call('save')
        ->assertHasErrors(['fin' => 'after']);

    remplirAnnonce(Livewire::actingAs($agent)->test('pages::annonces.form'), ['niveau' => 'apocalypse'])
        ->call('save')
        ->assertHasErrors(['niveau' => 'in']);

    expect(Annonce::count())->toBe(0);
});

test('le bandeau s’affiche sur toutes les pages pendant la période de diffusion seulement', function () {
    $this->travelTo(now()->setDateTime(2026, 10, 3, 12, 0));
    Annonce::factory()->create([
        'titre' => 'Alerte météo sur Nova Terra',
        'debut' => now()->addHour(),
        'fin' => now()->addHours(3),
    ]);
    $citoyen = User::factory()->citoyen()->create();
    $agent = User::factory()->agent()->create();

    // Avant le début.
    $this->get(route('home'))->assertDontSee('Alerte météo sur Nova Terra');

    // Pendant : page publique (invité), connexion, espace citoyen, espace agent.
    $this->travel(2)->hours();
    $this->get(route('home'))->assertSee('Alerte météo sur Nova Terra');
    $this->get(route('login'))->assertSee('Alerte météo sur Nova Terra');
    $this->actingAs($citoyen)->get(route('dashboard'))->assertSee('Alerte météo sur Nova Terra');
    $this->actingAs($agent)->get(route('agent.annonces.create'))->assertSee('Alerte météo sur Nova Terra');
    auth()->logout();

    // Après la fin.
    $this->travel(2)->hours();
    $this->get(route('home'))->assertDontSee('Alerte météo sur Nova Terra');
});

test('le bandeau s’affiche aussi sur la page 404', function () {
    Annonce::factory()->active()->create(['titre' => 'Message visible partout']);

    $this->get('/page-qui-n-existe-pas')->assertNotFound()->assertSee('Message visible partout');
});

test('le contenu du bandeau est échappé', function () {
    Annonce::factory()->active()->create([
        'titre' => '<script>alert("titre")</script>',
        'contenu' => '<script>alert("xss")</script>',
    ]);

    $this->get(route('home'))
        ->assertDontSee('<script>alert("xss")</script>', false)
        ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
});

test('le bandeau propose un bouton de fermeture mémorisé par message et par version', function () {
    $annonce = Annonce::factory()->active()->create(['titre' => 'Coupure d’eau', 'niveau' => 'alerte']);

    $this->get(route('home'))
        ->assertSee('aria-label="Fermer le message : Coupure d’eau"', false)
        ->assertSee($annonce->cleFermeture(), false)
        ->assertSee('role="alert"', false);
});

test('une modification est visible tout de suite malgré le cache', function () {
    $annonce = Annonce::factory()->active()->create(['titre' => 'Ancien titre']);
    $this->get(route('home'))->assertSee('Ancien titre');

    $annonce->update(['titre' => 'Nouveau titre']);

    $this->get(route('home'))->assertSee('Nouveau titre')->assertDontSee('Ancien titre');
});

test('le bandeau fonctionne avec un cache qui sérialise, sans désérialiser d’objet', function () {
    // Comme en production (cache en base) : le contenu est sérialisé et aucune classe n'est autorisée à la lecture.
    config(['cache.stores.array.serialize' => true, 'cache.serializable_classes' => false]);
    Cache::forgetDriver('array');
    Annonce::factory()->active()->create(['titre' => 'Message mis en cache']);

    $this->get(route('home'))->assertOk()->assertSee('Message mis en cache');
    $this->get(route('home'))->assertOk()->assertSee('Message mis en cache');
});

test('les messages en diffusion sont triés du plus grave au moins grave', function () {
    Annonce::factory()->active()->create(['niveau' => 'information', 'titre' => 'Info']);
    Annonce::factory()->active()->create(['niveau' => 'alerte', 'titre' => 'Urgence']);
    Annonce::factory()->expiree()->create(['niveau' => 'alerte', 'titre' => 'Expirée']);

    expect(Annonce::enDiffusion()->pluck('titre')->all())->toBe(['Urgence', 'Info']);
});
