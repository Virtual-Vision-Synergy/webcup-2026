<?php

use App\Models\Annonce;
use App\Models\Quartier;
use App\Models\User;

/*
 * UX des bandeaux de messages (D18 / F29) : rendu unique dans chaque gabarit, disparition automatique,
 * mode compact avec « Voir plus », liens toujours présents. Le comportement JS (minuteur, pause, dépliage) est vérifié à la main.
 */

test('le bandeau est rendu une seule fois dans les gabarits invité, citoyen et agent', function () {
    $annonce = Annonce::factory()->active()->create(['titre' => 'Coupure d’eau générale']);
    $attribut = 'data-annonce-cle="'.$annonce->cleFermeture().'"';

    $invite = $this->get(route('home'))->assertOk()->getContent();
    $citoyen = $this->actingAs(User::factory()->citoyen()->create())->get(route('dashboard'))->assertOk()->getContent();
    $agent = $this->actingAs(User::factory()->agent()->create())->get(route('agent.tableau-de-bord'))->assertOk()->getContent();

    expect(substr_count($invite, $attribut))->toBe(1)
        ->and(substr_count($citoyen, $attribut))->toBe(1)
        ->and(substr_count($agent, $attribut))->toBe(1);
});

test('le bandeau porte la durée d’affichage et passe en mode compact pour un texte long', function () {
    Annonce::factory()->active()->create(['contenu' => str_repeat('Texte très long du message. ', 20)]);

    $this->get(route('home'))
        ->assertSee('data-duree-affichage="10"', false)
        ->assertSee('line-clamp-2', false)
        ->assertSee('Voir plus')
        ->assertSee('aria-expanded="false"', false);
});

test('les consignes sont derrière « Voir plus » et le lien de l’alerte reste visible', function () {
    $alerte = Annonce::factory()->active()->create([
        'niveau' => 'alerte',
        'contenu' => 'Court.',
        'quartier_id' => Quartier::idPour('sud'),
        'consignes' => 'Éloignez-vous des berges.',
    ]);
    $habitantSud = User::factory()->citoyen()->quartier('sud')->create();

    $this->actingAs($habitantSud)->get(route('home'))
        ->assertSee('Voir plus')
        ->assertSee('aria-controls="tn-annonce-'.$alerte->cleFermeture().'-details"', false)
        ->assertSee('Éloignez-vous des berges.')
        ->assertSee(route('alertes.show', $alerte->id), false);
});

test('un texte court sans consignes n’a pas de bouton « Voir plus »', function () {
    Annonce::factory()->active()->create(['contenu' => 'Court.']);

    $this->get(route('home'))->assertDontSee('Voir plus');
});

test('le contenu HTML d’un bandeau est échappé', function () {
    Annonce::factory()->active()->create(['contenu' => '<a href="https://pirate.test">piège</a>']);

    $this->get(route('home'))
        ->assertDontSee('href="https://pirate.test"', false)
        ->assertSee('&lt;a href=&quot;https://pirate.test&quot;&gt;', false);
});

test('un agent sans quartier n’est pas invité à renseigner son quartier', function () {
    Annonce::factory()->active()->create(['quartier_id' => Quartier::idPour('sud')]);
    $agent = User::factory()->agent()->create(['quartier_id' => null]);

    $this->actingAs($agent)->get(route('agent.tableau-de-bord'))
        ->assertOk()
        ->assertDontSee('Indiquez votre quartier pour recevoir les alertes qui vous concernent');
});
