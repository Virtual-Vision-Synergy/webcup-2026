<?php

use App\Models\Annonce;
use App\Models\User;

/*
 * F44 : le contenu peut être agrandi (zoom navigateur 200 % / 400 %) sans casser l'affichage.
 * Le reflow lui-même a été vérifié dans Chromium à 640 px et 320 px de large ; ces tests gardent les causes corrigées.
 */

test('le zoom du navigateur n’est jamais bloqué', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('width=device-width', false)
        ->assertDontSee('user-scalable', false)
        ->assertDontSee('maximum-scale', false);
});

test('dans l’espace agent, le bandeau d’alerte reste au-dessus du contenu au lieu de former une colonne étroite', function () {
    Annonce::factory()->active()->create(['titre' => 'Coupure d’eau générale']);

    $reponse = $this->actingAs(User::factory()->agent()->create())->get(route('agent.tableau-de-bord'));

    $reponse->assertOk()
        ->assertSeeInOrder(['Coupure d’eau générale', '<main id="contenu"'], false)
        ->assertDontSee('data-flux-main', false);
});

test('les zones de tableau défilent seules et les textes tronqués passent à la ligne sur petite largeur', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toMatch('/ui-table-scroll-area\s*\{\s*position:\s*relative;/')
        ->toMatch('/@media \(max-width: 64rem\)\s*\{\s*\.truncate\s*\{[^}]*white-space:\s*normal;/');
});
