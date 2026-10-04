<?php

use App\Models\Idea;
use App\Models\IdeaSupport;
use App\Models\User;
use App\Notifications\Avis;
use Illuminate\Support\Facades\Notification;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;

test('un invité lit les idées publiées mais doit se connecter pour proposer', function () {
    Idea::factory()->create(['title' => 'Des bancs ombragés près du marché']);

    $this->get(route('ideas.index'))->assertOk()->assertSee('Des bancs ombragés près du marché');
    $this->get(route('ideas.create'))->assertRedirect(route('login'));
});

test('un invité qui veut soutenir est renvoyé vers la connexion', function () {
    $idea = Idea::factory()->create();

    Livewire::test('pages::ideas.show', ['idea' => $idea])
        ->call('soutenir')
        ->assertRedirect(route('login'));

    expect($idea->soutiens()->count())->toBe(0);
});

test('un citoyen propose une idée et reçoit un numéro de suivi et un avis', function () {
    Notification::fake();
    $citoyen = User::factory()->citoyen()->create();

    Livewire::actingAs($citoyen)
        ->test('pages::ideas.form')
        ->set('title', 'Une navette le dimanche')
        ->set('description', 'Relier les quartiers au centre le dimanche, toutes les heures.')
        ->set('category', 'mobilite')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('ideas.received', Idea::first()));

    $idea = Idea::sole();
    expect($idea->reference)->toMatch('/^IDE-\d{4}-\d{6}$/')
        ->and($idea->status)->toBe('recue')
        ->and($idea->user_id)->toBe($citoyen->id);

    Notification::assertSentTo($citoyen, Avis::class, fn (Avis $avis) => str_contains($avis->sujet, $idea->reference));

    $this->actingAs($citoyen)->get(route('ideas.received', $idea))
        ->assertOk()
        ->assertSee([$idea->reference, 'Reçue']);
});

test('le formulaire n’expose aucun champ réservé', function (string $champ) {
    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::ideas.form')
        ->set($champ, 'retenue');
})->with(['status', 'reference', 'user_id', 'response', 'hidden_at'])
    ->throws(PublicPropertyNotFoundException::class);

test('les champs réservés passés à la création sont ignorés', function () {
    $citoyen = User::factory()->citoyen()->create();
    $autre = User::factory()->create();

    $idea = Idea::proposer($citoyen, [
        'title' => 'Un composteur collectif',
        'description' => 'Un bac à compost par quartier.',
        'category' => 'environnement',
        'status' => 'retenue', 'response' => 'Oui', 'user_id' => $autre->id, 'hidden_at' => now(), 'reference' => 'X',
    ]);

    expect($idea->fresh())
        ->status->toBe('recue')
        ->response->toBeNull()
        ->hidden_at->toBeNull()
        ->user_id->toBe($citoyen->id)
        ->reference->toMatch('/^IDE-\d{4}-\d{6}$/');
});

test('un citoyen soutient une idée une seule fois', function () {
    $idea = Idea::factory()->create();
    $citoyen = User::factory()->citoyen()->create();

    Livewire::actingAs($citoyen)
        ->test('pages::ideas.index')
        ->call('soutenir', $idea->id)
        ->call('soutenir', $idea->id)
        ->assertHasNoErrors()
        ->assertSee('Vous soutenez cette idée');

    expect($idea->soutiens()->count())->toBe(1);
});

test('un citoyen peut retirer son soutien', function () {
    $soutien = IdeaSupport::factory()->create();

    Livewire::actingAs($soutien->user)
        ->test('pages::ideas.show', ['idea' => $soutien->idea])
        ->call('retirerSoutien')
        ->assertHasNoErrors();

    expect(IdeaSupport::count())->toBe(0);
});

test('soutenir sa propre idée, une idée tranchée ou en tant qu’agent renvoie 403', function (array $cas) {
    [$idea, $user] = $cas;

    Livewire::actingAs($user)
        ->test('pages::ideas.index')
        ->call('soutenir', $idea->id)
        ->assertForbidden();

    expect($idea->soutiens()->count())->toBe(0);
})->with([
    'sa propre idée' => fn () => [$idea = Idea::factory()->create(), $idea->user],
    'idée non retenue' => fn () => [Idea::factory()->nonRetenue()->create(), User::factory()->citoyen()->create()],
    'agent' => fn () => [Idea::factory()->create(), User::factory()->agent()->create()],
]);

test('un citoyen, même auteur, ne peut ni changer l’état, ni répondre, ni masquer', function () {
    $idea = Idea::factory()->create();

    foreach ([$idea->user, User::factory()->citoyen()->create()] as $citoyen) {
        $this->actingAs($citoyen)->get(route('agent.ideas.show', $idea))->assertForbidden();

        expect($citoyen->can('updateStatus', $idea))->toBeFalse()
            ->and($citoyen->can('respond', $idea))->toBeFalse()
            ->and($citoyen->can('hide', $idea))->toBeFalse();
    }

    expect($idea->fresh()->status)->toBe('recue');
});

test('l’agent voit les idées triées par soutiens décroissants', function () {
    $peu = Idea::factory()->create(['title' => 'Idée peu soutenue']);
    $beaucoup = Idea::factory()->create(['title' => 'Idée populaire']);
    IdeaSupport::factory()->for($peu)->create();
    IdeaSupport::factory(3)->for($beaucoup)->create();

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.ideas.index'))
        ->assertOk()
        ->assertSeeInOrder(['Idée populaire', 'Idée peu soutenue']);
});

test('l’agent retient une idée avec une réponse et l’auteur est prévenu', function () {
    Notification::fake();
    $idea = Idea::factory()->create();

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::agent.ideas.show', ['idea' => $idea])
        ->set('status', 'retenue')
        ->set('response', 'Six bancs seront installés le mois prochain.')
        ->call('enregistrer')
        ->assertHasNoErrors();

    expect($idea->fresh())
        ->status->toBe('retenue')
        ->response->toBe('Six bancs seront installés le mois prochain.')
        ->responded_at->not->toBeNull();

    Notification::assertSentTo($idea->user, Avis::class);

    $this->get(route('ideas.show', $idea))->assertSee(['Retenue', 'Six bancs seront installés le mois prochain.']);
});

test('la réponse est obligatoire pour retenir ou non une idée', function () {
    $idea = Idea::factory()->create();

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::agent.ideas.show', ['idea' => $idea])
        ->set('status', 'non_retenue')
        ->set('response', '')
        ->call('enregistrer')
        ->assertHasErrors(['response' => 'required']);

    expect($idea->fresh()->status)->toBe('recue');
});

test('une idée masquée disparaît de la page publique mais reste visible par son auteur', function () {
    $idea = Idea::factory()->create(['title' => 'Idée à modérer']);

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::agent.ideas.show', ['idea' => $idea])
        ->call('masquer');
    auth()->logout();

    $this->get(route('ideas.index'))->assertDontSee('Idée à modérer');
    $this->get(route('ideas.show', $idea))->assertNotFound();
    $this->actingAs(User::factory()->citoyen()->create())->get(route('ideas.show', $idea))->assertNotFound();
    $this->actingAs($idea->user)->get(route('ideas.show', $idea))->assertOk()->assertSee('Masquée par la modération');
});

test('l’accusé de réception d’un autre habitant renvoie 403', function () {
    $idea = Idea::factory()->create();

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('ideas.received', $idea))
        ->assertForbidden();
});

test('un titre ou une description avec du HTML est affiché échappé', function () {
    $idea = Idea::factory()->create(['title' => '<script>alert(1)</script>', 'description' => '<script>alert(2)</script>']);

    $this->get(route('ideas.show', $idea))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<script>alert(2)</script>', false)
        ->assertSee('&lt;script&gt;alert(2)&lt;/script&gt;', false);
});
