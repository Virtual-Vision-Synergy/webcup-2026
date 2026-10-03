<?php

use App\Models\Signalement;
use App\Models\Soutien;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Livewire\Livewire;

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('signalements.index', ['vue' => 'publiques']))->assertRedirect(route('login'));
});

test('un citoyen voit les demandes ouvertes des autres habitants, sans leur auteur', function () {
    $auteur = User::factory()->citoyen()->create(['name' => 'Auteur Secret']);
    Signalement::factory()->for($auteur)->create(['statut' => 'nouveau', 'lieu' => 'Rue des Lumières']);
    Signalement::factory()->for($auteur)->create(['statut' => 'resolu', 'lieu' => 'Place déjà réparée']);

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('signalements.index', ['vue' => 'publiques']))
        ->assertOk()
        ->assertSee(['Rue des Lumières', 'Je soutiens'])
        ->assertDontSee(['Place déjà réparée', 'Auteur Secret']);
});

test('un citoyen soutient une demande une seule fois et le bouton change', function () {
    $signalement = Signalement::factory()->nouveau()->create();
    $citoyen = User::factory()->citoyen()->create();

    Livewire::actingAs($citoyen)
        ->test('pages::signalements.index')
        ->set('vue', 'publiques')
        ->call('soutenir', $signalement->id)
        ->call('soutenir', $signalement->id)
        ->assertHasNoErrors()
        ->assertSee('Vous soutenez cette demande');

    expect($signalement->soutiens()->count())->toBe(1);
});

test('la base refuse un second soutien du même habitant', function () {
    $soutien = Soutien::factory()->create();

    $doublon = new Soutien;
    $doublon->user_id = $soutien->user_id;
    $doublon->signalement_id = $soutien->signalement_id;

    expect(fn () => $doublon->save())->toThrow(UniqueConstraintViolationException::class);
});

test('un citoyen peut retirer son soutien', function () {
    $soutien = Soutien::factory()->create();

    Livewire::actingAs($soutien->user)
        ->test('pages::signalements.index')
        ->set('vue', 'publiques')
        ->call('retirerSoutien', $soutien->signalement_id)
        ->assertHasNoErrors()
        ->assertSee('Je soutiens');

    expect(Soutien::count())->toBe(0);
});

test('retirer le soutien d’un autre habitant renvoie 403', function () {
    $soutien = Soutien::factory()->create();

    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::signalements.index')
        ->set('vue', 'publiques')
        ->call('retirerSoutien', $soutien->signalement_id)
        ->assertForbidden();

    expect(Soutien::count())->toBe(1);
});

test('soutenir sa propre demande renvoie 403', function () {
    $citoyen = User::factory()->citoyen()->create();
    $signalement = Signalement::factory()->nouveau()->for($citoyen)->create();

    Livewire::actingAs($citoyen)
        ->test('pages::signalements.index')
        ->call('soutenir', $signalement->id)
        ->assertForbidden();

    expect($signalement->soutiens()->count())->toBe(0);
});

test('soutenir une demande clôturée renvoie 403', function () {
    $signalement = Signalement::factory()->create(['statut' => 'resolu']);

    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::signalements.index')
        ->call('soutenir', $signalement->id)
        ->assertForbidden();

    expect($signalement->soutiens()->count())->toBe(0);
});

test('un agent ne peut pas soutenir une demande', function () {
    $signalement = Signalement::factory()->nouveau()->create();

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::signalements.index')
        ->call('soutenir', $signalement->id)
        ->assertForbidden();
});

test('l’agent voit le nombre de soutiens et peut trier les demandes par soutiens', function () {
    $peuSoutenue = Signalement::factory()->nouveau()->create(['lieu' => 'Rue peu soutenue']);
    $tresSoutenue = Signalement::factory()->nouveau()->create(['lieu' => 'Avenue populaire']);
    Soutien::factory()->for($peuSoutenue)->create();
    Soutien::factory(3)->for($tresSoutenue)->create();

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::signalements.index')
        ->set('tri', 'soutiens')
        ->assertSee('Soutiens')
        ->assertSeeInOrder(['Avenue populaire', 'Rue peu soutenue']);
});

test('l’auteur voit combien d’habitants soutiennent sa demande', function () {
    $citoyen = User::factory()->citoyen()->create();
    $signalement = Signalement::factory()->nouveau()->for($citoyen)->create();
    Soutien::factory(2)->for($signalement)->create();

    $this->actingAs($citoyen)
        ->get(route('signalements.show', $signalement))
        ->assertOk()
        ->assertSeeText('2 autres habitants soutiennent');
});
