<?php

use App\Models\Remontee;
use App\Models\User;
use App\Notifications\Avis;
use Illuminate\Support\Facades\Notification;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
});

/**
 * Envoie une remontée par le formulaire, comme un habitant.
 */
function envoyerRemontee(User $user, array $champs = []): Remontee
{
    Livewire::actingAs($user)
        ->test('pages::remontees.form')
        ->set('categorie', $champs['categorie'] ?? 'comprendre')
        ->set('objet', $champs['objet'] ?? 'Qui voit mon téléphone ?')
        ->set('message', $champs['message'] ?? 'Je voudrais savoir qui peut voir mon numéro de téléphone.')
        ->call('save')
        ->assertHasNoErrors();

    return Remontee::query()->latest('id')->firstOrFail();
}

// --- Page « Vos données » ---

test('la page « Vos données » est accessible sans compte avec ses rubriques et le lien de suppression', function () {
    $this->get(route('privacy.show'))
        ->assertOk()
        ->assertSee(['Quelles données ?', 'Pourquoi ?', 'Combien de temps ?', 'Qui peut les voir ?', 'Comment les supprimer ?', 'Une question ou une inquiétude ?'])
        ->assertSee(route('profile.edit'), false)
        ->assertSee(route('concerns.create'), false)
        ->assertSee(config('security.login.retention_days').' jours')
        ->assertSee('À préciser par la Mairie');
});

test('la page d’inscription et le profil renvoient vers « Vos données »', function () {
    $this->get(route('register'))->assertSee(route('privacy.show'), false);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertSee(route('privacy.show'), false);
});

// --- Parcours citoyen ---

test('un invité est redirigé vers la connexion', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['concerns.create', 'concerns.index']);

test('un citoyen envoie une remontée : numéro de suivi, état Reçue, accusé de réception et avis', function () {
    $user = User::factory()->citoyen()->create();

    Livewire::actingAs($user)
        ->test('pages::remontees.form')
        ->set('categorie', 'acceder')
        ->set('objet', 'Voir mes données')
        ->set('message', 'Je voudrais voir tout ce que la mairie garde sur moi.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('concerns.received', Remontee::query()->latest('id')->first()));

    $remontee = Remontee::query()->latest('id')->firstOrFail();

    expect($remontee->reference)->toMatch('/^DON-\d{4}-\d{6}$/')
        ->and($remontee->reference)->toBe(sprintf('DON-%s-%06d', now()->format('Y'), $remontee->id))
        ->and($remontee->statut)->toBe('recue')
        ->and($remontee->user_id)->toBe($user->id)
        ->and($remontee->envoyee_le)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('concerns.received', $remontee))
        ->assertOk()
        ->assertSee([$remontee->reference, 'Et maintenant ?']);

    Notification::assertSentTo($user, Avis::class, function (Avis $avis, array $canaux) use ($remontee) {
        return str_contains($avis->sujet, $remontee->reference) && in_array('mail', $canaux, true) && in_array('database', $canaux, true);
    });
});

test('deux remontées ont des numéros de suivi différents', function () {
    $user = User::factory()->create();

    $a = envoyerRemontee($user);
    $b = envoyerRemontee($user);

    expect($a->reference)->not->toBe($b->reference);
});

test('les champs réservés envoyés avec la remontée sont ignorés', function () {
    $user = User::factory()->create();
    $autre = User::factory()->agent()->create();

    $remontee = Remontee::envoyer($user, [
        'categorie' => 'autre',
        'objet' => 'Test',
        'message' => 'Message de test assez long.',
        'reference' => 'DON-1999-000001',
        'statut' => 'cloturee',
        'user_id' => $autre->id,
        'reponse' => 'Réponse pirate',
        'prise_en_compte_le' => now(),
        'pris_en_charge_par' => $autre->id,
        'repondue_le' => now(),
        'cloturee_le' => now(),
    ]);

    expect($remontee->fresh())
        ->reference->not->toBe('DON-1999-000001')
        ->statut->toBe('recue')
        ->user_id->toBe($user->id)
        ->reponse->toBeNull()
        ->prise_en_compte_le->toBeNull()
        ->pris_en_charge_par->toBeNull()
        ->repondue_le->toBeNull()
        ->cloturee_le->toBeNull();
});

test('le formulaire n’expose aucun champ réservé', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::remontees.form')
        ->set('statut', 'cloturee');
})->throws(PublicPropertyNotFoundException::class);

test('le formulaire valide les champs avec des messages en français', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::remontees.form')
        ->set('categorie', 'pirate')
        ->set('objet', '')
        ->set('message', 'court')
        ->call('save')
        ->assertHasErrors(['categorie', 'objet', 'message'])
        ->assertSee('Indiquez l’objet de votre remontée');

    expect(Remontee::count())->toBe(0);
});

test('l’envoi est limité contre l’abus', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        envoyerRemontee($user);
    }

    Livewire::actingAs($user)
        ->test('pages::remontees.form')
        ->set('objet', 'Encore une')
        ->set('message', 'Encore une remontée de trop.')
        ->call('save')
        ->assertHasErrors('throttle');

    expect(Remontee::count())->toBe(5);
});

test('le citoyen A reçoit un 403 sur la remontée de B, qui n’apparaît pas dans sa liste', function () {
    $a = User::factory()->create();
    $remonteeDeB = Remontee::factory()->create(['objet' => 'Remontée privée de B']);
    Remontee::factory()->for($a)->create(['objet' => 'Ma propre remontée']);

    $this->actingAs($a)->get(route('concerns.show', $remonteeDeB))->assertForbidden();
    $this->actingAs($a)->get(route('concerns.received', $remonteeDeB))->assertForbidden();

    $this->actingAs($a)
        ->get(route('concerns.index'))
        ->assertOk()
        ->assertSee('Ma propre remontée')
        ->assertDontSee('Remontée privée de B');
});

test('un citoyen reçoit un 403 sur toutes les routes agent des remontées', function () {
    $citoyen = User::factory()->citoyen()->create();
    $remontee = Remontee::factory()->for($citoyen)->create();

    $this->actingAs($citoyen)->get(route('agent.concerns.index'))->assertForbidden();
    $this->actingAs($citoyen)->get(route('agent.concerns.show', $remontee))->assertForbidden();
});

test('un citoyen ne peut pas appeler les actions agent', function (string $action) {
    $citoyen = User::factory()->citoyen()->create();
    $remontee = Remontee::factory()->repondue()->for($citoyen)->create();
    $agent = User::factory()->agent()->create();

    $composant = Livewire::actingAs($agent)->test('pages::agent.remontees.show', ['remontee' => $remontee]);

    // Même composant, mais l'utilisateur connecté devient un citoyen : chaque action revérifie les droits.
    $this->actingAs($citoyen);
    $composant->set('reponse', 'Réponse pirate du citoyen')->call($action)->assertForbidden();
})->with(['prendreEnCompte', 'repondre', 'cloturer']);

// --- Côté agent ---

test('un agent voit les remontées, les non traitées en premier, avec le compteur', function () {
    Remontee::factory()->repondue()->create(['objet' => 'Déjà répondue', 'envoyee_le' => now()->subDays(9)]);
    Remontee::factory()->create(['objet' => 'En attente récente', 'envoyee_le' => now()->subDay()]);

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.concerns.index'))
        ->assertOk()
        ->assertSeeInOrder(['En attente récente', 'Déjà répondue'])
        ->assertSee('1 remontée(s) en attente');
});

test('un agent filtre par état et par sujet', function () {
    Remontee::factory()->create(['objet' => 'Corriger mon quartier', 'categorie' => 'corriger']);
    Remontee::factory()->repondue()->create(['objet' => 'Supprimer mon compte', 'categorie' => 'supprimer']);

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::agent.remontees.index')
        ->set('filterCategorie', 'corriger')
        ->assertSee('Corriger mon quartier')
        ->assertDontSee('Supprimer mon compte')
        ->set('filterCategorie', '')
        ->set('filterStatut', 'repondue')
        ->assertSee('Supprimer mon compte')
        ->assertDontSee('Corriger mon quartier');
});

test('un agent prend en compte, répond puis clôture ; le citoyen est notifié et voit la frise', function () {
    $citoyen = User::factory()->citoyen()->create();
    $agent = User::factory()->agent()->create();
    $remontee = Remontee::factory()->for($citoyen)->create();

    $page = Livewire::actingAs($agent)->test('pages::agent.remontees.show', ['remontee' => $remontee]);

    $page->call('prendreEnCompte')->assertHasNoErrors();
    expect($remontee->fresh())
        ->statut->toBe('prise_en_compte')
        ->prise_en_compte_le->not->toBeNull()
        ->pris_en_charge_par->toBe($agent->id);

    $page->set('reponse', 'Votre numéro sert uniquement à vous joindre pour vos démarches.')->call('repondre')->assertHasNoErrors();
    expect($remontee->fresh())
        ->statut->toBe('repondue')
        ->reponse->toBe('Votre numéro sert uniquement à vous joindre pour vos démarches.')
        ->repondue_le->not->toBeNull()
        ->repondue_par->toBe($agent->id);

    $page->call('cloturer')->assertHasNoErrors();
    expect($remontee->fresh())
        ->statut->toBe('cloturee')
        ->cloturee_le->not->toBeNull();

    Notification::assertSentToTimes($citoyen, Avis::class, 3);

    $remontee->refresh();
    $this->actingAs($citoyen)
        ->get(route('concerns.show', $remontee))
        ->assertOk()
        ->assertSeeInOrder(['Envoyée', 'Prise en compte', 'Réponse', 'Clôturée'])
        ->assertSee('Votre numéro sert uniquement à vous joindre pour vos démarches.')
        ->assertSee(Remontee::dateLocale($remontee->repondue_le));
});

test('une remontée reçue ne peut pas être clôturée sans réponse', function () {
    $citoyen = User::factory()->create();
    $remontee = Remontee::factory()->for($citoyen)->create();

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::agent.remontees.show', ['remontee' => $remontee])
        ->call('cloturer')
        ->assertHasErrors('transition')
        ->assertSee('Une remontée ne peut être clôturée qu’après une réponse à l’habitant.');

    expect($remontee->fresh()->statut)->toBe('recue');
    Notification::assertNothingSentTo($citoyen);
});

test('une remontée déjà répondue ne peut pas recevoir une seconde réponse', function () {
    $remontee = Remontee::factory()->repondue()->create();
    $reponse = $remontee->reponse;

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::agent.remontees.show', ['remontee' => $remontee])
        ->set('reponse', 'Une autre réponse qui écraserait la première.')
        ->call('repondre')
        ->assertHasErrors('transition');

    expect($remontee->fresh()->reponse)->toBe($reponse);
});

test('un message contenant du HTML est affiché échappé côté citoyen et côté agent', function () {
    $citoyen = User::factory()->create();
    $remontee = Remontee::factory()->for($citoyen)->create(['message' => '<script>alert("x")</script>']);

    foreach ([[$citoyen, route('concerns.show', $remontee)], [User::factory()->agent()->create(), route('agent.concerns.show', $remontee)]] as [$user, $url]) {
        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertDontSee('<script>alert("x")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }
});

// --- Suppression du compte (comportement actuel de la suppression du starter kit) ---

test('à la suppression du compte, la remontée est conservée anonymisée', function () {
    $citoyen = User::factory()->create();
    $remontee = Remontee::factory()->for($citoyen)->create();

    $citoyen->delete();

    expect($remontee->fresh())
        ->not->toBeNull()
        ->user_id->toBeNull();

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.concerns.show', $remontee))
        ->assertOk()
        ->assertSee('Compte supprimé');
});
