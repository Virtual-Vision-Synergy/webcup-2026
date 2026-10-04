<?php

use App\Models\Demarche;
use App\Models\Service;
use App\Models\ServiceReview;
use App\Models\User;
use App\Notifications\Avis;
use Illuminate\Support\Facades\Notification;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;

test('un invité est renvoyé vers la connexion pour voir les avis ou donner le sien', function () {
    $service = Service::factory()->create();

    $this->get(route('services.reviews.index', $service))->assertRedirect(route('login'));
    $this->get(route('services.reviews.edit', $service))->assertRedirect(route('login'));
    $this->get(route('services.reviews.mine'))->assertRedirect(route('login'));
});

test('un citoyen donne son avis : enregistré, confirmation datée, présent dans « Mes avis »', function () {
    $this->travelTo(now()->setDate(2026, 10, 3)->setTime(11, 32)); // 14 h 32 à Nova Terra (UTC+3)
    $citoyen = User::factory()->citoyen()->create();
    $service = Service::factory()->create(['nom' => 'État civil']);

    Livewire::actingAs($citoyen)
        ->test('pages::service-reviews.form', ['service' => $service])
        ->set('rating', '4')
        ->set('comment', 'Accueil rapide et agent très aimable.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Merci, votre avis a été enregistré le samedi 3 octobre 2026 à 14 h 32.');

    $avis = ServiceReview::sole();
    expect($avis->user_id)->toBe($citoyen->id)
        ->and($avis->service_id)->toBe($service->id)
        ->and($avis->rating)->toBe(4);

    $this->actingAs($citoyen)->get(route('services.reviews.mine'))
        ->assertOk()
        ->assertSee('État civil')
        ->assertSee('Accueil rapide et agent très aimable.')
        ->assertSee('Publié');
});

test('un second envoi pour le même service met à jour l’avis sans doublon', function () {
    $citoyen = User::factory()->citoyen()->create();
    $service = Service::factory()->create();
    ServiceReview::factory()->for($citoyen)->for($service)->create(['rating' => 2, 'comment' => 'Premier avis assez mitigé.']);

    Livewire::actingAs($citoyen)
        ->test('pages::service-reviews.form', ['service' => $service])
        ->assertSet('rating', '2')
        ->set('rating', '5')
        ->set('comment', 'Finalement tout s’est très bien passé.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Merci, votre avis a été mis à jour le');

    expect(ServiceReview::count())->toBe(1)
        ->and(ServiceReview::sole()->rating)->toBe(5);
});

test('une note hors de 1 à 5 ou un commentaire vide sont refusés en français', function (string $rating, string $comment, string $champ, string $message) {
    $service = Service::factory()->create();

    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::service-reviews.form', ['service' => $service])
        ->set('rating', $rating)
        ->set('comment', $comment)
        ->call('save')
        ->assertHasErrors($champ)
        ->assertSee($message);

    expect(ServiceReview::count())->toBe(0);
})->with([
    'note 0' => ['0', 'Un commentaire valide.', 'rating', 'Choisissez une note de 1 à 5.'],
    'note 6' => ['6', 'Un commentaire valide.', 'rating', 'Choisissez une note de 1 à 5.'],
    'commentaire vide' => ['3', '', 'comment', 'Écrivez un commentaire pour expliquer votre note.'],
]);

test('les champs réservés envoyés par le navigateur sont refusés', function (string $champ, mixed $valeur) {
    $service = Service::factory()->create();

    expect(fn () => Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::service-reviews.form', ['service' => $service])
        ->set($champ, $valeur))
        ->toThrow(PublicPropertyNotFoundException::class);
})->with([
    'user_id' => ['user_id', 1],
    'service_id' => ['service_id', 99],
    'verified_usage' => ['verified_usage', true],
    'hidden_at' => ['hidden_at', '2026-10-03'],
    'response' => ['response', 'Réponse falsifiée'],
]);

test('l’assignation de masse ignore les champs réservés', function () {
    $avis = new ServiceReview([
        'rating' => 4, 'comment' => 'Texte', 'user_id' => 1, 'service_id' => 2, 'verified_usage' => true,
        'hidden_at' => now(), 'response' => 'x', 'responded_by' => 1,
    ]);

    expect($avis->getAttributes())->toBe(['rating' => 4, 'comment' => 'Texte']);
});

test('« A utilisé ce service » seulement avec une démarche traitée pour ce service', function () {
    $citoyen = User::factory()->citoyen()->create();
    $service = Service::factory()->create();
    $autre = Service::factory()->create();
    Demarche::factory()->for($citoyen)->for($autre)->create(['statut' => 'traitee']);
    Demarche::factory()->for($citoyen)->for($service)->create(['statut' => 'en_cours']);

    expect(ServiceReview::usageVerifie($citoyen, $service))->toBeFalse();

    Demarche::factory()->for($citoyen)->for($service)->create(['statut' => 'traitee']);

    $avis = ServiceReview::enregistrer($citoyen, $service, ['rating' => 5, 'comment' => 'Démarche bien suivie.']);
    expect($avis->verified_usage)->toBeTrue();
});

test('un citoyen ne peut pas modifier l’avis d’un autre', function () {
    $avisDeB = ServiceReview::factory()->create();

    expect(User::factory()->citoyen()->create()->can('update', $avisDeB))->toBeFalse()
        ->and($avisDeB->user->can('update', $avisDeB))->toBeTrue();
});

test('un citoyen n’a pas accès à la modération des avis', function () {
    $this->actingAs(User::factory()->citoyen()->create())->get(route('agent.reviews.index'))->assertForbidden();
});

test('un agent d’un autre service → 403 pour répondre, masquer ou réafficher', function (string $action) {
    $avis = ServiceReview::factory()->create();
    $agentAutreService = User::factory()->agentDe(Service::factory()->create())->create();

    Livewire::actingAs($agentAutreService)
        ->test('pages::agent.service-reviews.index')
        ->assertDontSee($avis->comment)
        ->call($action, $avis->id)
        ->assertForbidden();

    expect($avis->fresh()->hidden_at)->toBeNull()->and($avis->fresh()->response)->toBeNull();
})->with(['ouvrirReponse', 'ouvrirMasquage', 'reafficher']);

test('l’agent du service répond et l’habitant est prévenu', function () {
    Notification::fake();
    $service = Service::factory()->create();
    $avis = ServiceReview::factory()->for($service)->create();

    Livewire::actingAs(User::factory()->agentDe($service)->create())
        ->test('pages::agent.service-reviews.index')
        ->assertSee($avis->comment)
        ->call('ouvrirReponse', $avis->id)
        ->set('response', 'Merci, nous avons ouvert un second guichet.')
        ->call('repondre')
        ->assertHasNoErrors();

    expect($avis->fresh()->response)->toBe('Merci, nous avons ouvert un second guichet.');
    Notification::assertSentTo($avis->user, Avis::class);
});

test('un admin masque un avis avec motif obligatoire, puis le réaffiche', function () {
    Notification::fake();
    $avis = ServiceReview::factory()->create();
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::agent.service-reviews.index')
        ->call('ouvrirMasquage', $avis->id)
        ->call('masquer')
        ->assertHasErrors(['hiddenReason' => 'required'])
        ->set('hiddenReason', 'injurieux')
        ->call('masquer')
        ->assertHasNoErrors();

    expect($avis->fresh()->estMasque())->toBeTrue()->and($avis->fresh()->hidden_reason)->toBe('injurieux');
    Notification::assertSentTo($avis->user, Avis::class);

    Livewire::actingAs($admin)->test('pages::agent.service-reviews.index')->call('reafficher', $avis->id);

    expect($avis->fresh()->estMasque())->toBeFalse();
});

test('un avis masqué sort de la moyenne et de la fiche, reste visible par son auteur et n’est plus modifiable', function () {
    $service = Service::factory()->create();
    ServiceReview::factory()->for($service)->create(['rating' => 4, 'comment' => 'Avis publié et correct.']);
    $masque = ServiceReview::factory()->for($service)->masque('injurieux')->create(['rating' => 1, 'comment' => 'Commentaire abusif masqué.']);

    expect($service->statistiquesAvis()['moyenne'])->toBe(4.0)
        ->and($service->statistiquesAvis()['total'])->toBe(1);

    $this->actingAs(User::factory()->citoyen()->create())->get(route('services.show', $service))
        ->assertSee('4,0')
        ->assertSee('Avis publié et correct.')
        ->assertDontSee('Commentaire abusif masqué.');

    $this->actingAs($masque->user)->get(route('services.reviews.mine'))
        ->assertSee('Commentaire abusif masqué.')
        ->assertSee('Masqué par la modération')
        ->assertSee('Propos injurieux');

    expect($masque->user->can('update', $masque))->toBeFalse();

    Livewire::actingAs($masque->user)
        ->test('pages::service-reviews.form', ['service' => $service])
        ->set('comment', 'Je retente ma chance.')
        ->call('save')
        ->assertForbidden();
});

test('la fiche affiche la moyenne, le badge et le prénom de l’auteur, jamais son e-mail ni son nom complet', function () {
    $service = Service::factory()->create();
    $auteur = User::factory()->citoyen()->create(['name' => 'Hanitra Rakotomalala', 'email' => 'hanitra.secret@example.com']);
    ServiceReview::factory()->for($auteur)->for($service)->create(['rating' => 5, 'verified_usage' => true, 'comment' => 'Très bon accueil au guichet.']);
    ServiceReview::factory()->for($service)->create(['rating' => 4]);

    $this->actingAs(User::factory()->citoyen()->create())->get(route('services.show', $service))
        ->assertOk()
        ->assertSee('4,5')
        ->assertSee('2 avis')
        ->assertSee('A utilisé ce service')
        ->assertSee('Hanitra R.')
        ->assertDontSee('Rakotomalala')
        ->assertDontSee('hanitra.secret@example.com')
        ->assertSee('Donner mon avis');
});

test('sans avis, la fiche affiche « Aucun avis pour le moment »', function () {
    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('services.show', Service::factory()->create()))
        ->assertSee('Aucun avis pour le moment.');
});

test('un commentaire contenant du HTML est affiché échappé', function () {
    $service = Service::factory()->create();
    ServiceReview::factory()->for($service)->create(['comment' => '<script>alert("xss")</script>']);

    $this->actingAs(User::factory()->citoyen()->create())->get(route('services.reviews.index', $service))
        ->assertDontSee('<script>alert("xss")</script>', false)
        ->assertSee('&lt;script&gt;', false);
});

test('une démarche traitée invite son auteur à donner son avis', function () {
    $citoyen = User::factory()->citoyen()->create();
    $demarche = Demarche::factory()->for($citoyen)->create(['statut' => 'traitee']);

    $this->actingAs($citoyen)->get(route('demarches.show', $demarche))
        ->assertSee('Comment s’est passée votre démarche ? Donnez votre avis')
        ->assertSee(route('services.reviews.edit', $demarche->service));
});
