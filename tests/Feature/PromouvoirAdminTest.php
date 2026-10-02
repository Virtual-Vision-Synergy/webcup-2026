<?php

use App\Models\User;

test('app:promouvoir-admin donne le rôle admin à un utilisateur existant', function () {
    $user = User::factory()->create(['email' => 'futur.admin@example.com']);

    expect($user->isAdmin())->toBeFalse();

    $this->artisan('app:promouvoir-admin', ['email' => 'futur.admin@example.com'])
        ->expectsOutputToContain('est maintenant administrateur')
        ->assertSuccessful();

    expect($user->fresh()->isAdmin())->toBeTrue();
});

test('app:promouvoir-admin refuse un e-mail inconnu et ne crée personne', function () {
    $this->artisan('app:promouvoir-admin', ['email' => 'inconnu@example.com'])
        ->expectsOutputToContain('Aucun utilisateur')
        ->assertFailed();

    expect(User::where('email', 'inconnu@example.com')->exists())->toBeFalse();
});

test('app:promouvoir-admin est sans effet sur un admin existant', function () {
    $admin = User::factory()->admin()->create();

    $this->artisan('app:promouvoir-admin', ['email' => $admin->email])
        ->expectsOutputToContain('déjà administrateur')
        ->assertSuccessful();
});
