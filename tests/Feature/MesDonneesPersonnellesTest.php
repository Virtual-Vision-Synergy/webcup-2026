<?php

use App\Models\User;

/**
 * F55 : « Télécharger mes données » (page imprimable /profil/mes-donnees).
 */
test('le propriétaire voit le document de ses données avec toutes les rubriques', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.data'))
        ->assertOk()
        ->assertSee($user->name)
        ->assertSee('Ce qu\'il faut retenir', false)
        ->assertSee('Mes démarches')
        ->assertSee('Mes appareils')
        ->assertSee('Rien à signaler dans cette rubrique.');
});

test('un invité est renvoyé vers la connexion', function () {
    $this->get(route('profile.data'))->assertRedirect(route('login'));
});
