<?php

use App\Models\Demarche;
use App\Models\KnownDevice;
use App\Models\Message;
use App\Models\Remontee;
use App\Models\RendezVous;
use App\Models\Signalement;
use App\Models\User;
use App\Notifications\Avis;

/*
| F69 (IDOR) : changer l'identifiant dans l'URL ne donne jamais accès à la ressource d'un autre habitant.
*/

test('un autre habitant reçoit 403 en changeant l’identifiant dans l’URL', function (string $route, Closure $creer) {
    $ressource = $creer();

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route($route, $ressource))
        ->assertForbidden();
})->with([
    'démarche' => ['demarches.show', fn () => Demarche::factory()->create()],
    'rendez-vous' => ['appointments.show', fn () => RendezVous::factory()->create()],
    'remontée' => ['concerns.show', fn () => Remontee::factory()->create()],
    'signalement' => ['signalements.show', fn () => Signalement::factory()->create()],
    'photo de signalement' => ['signalements.photo', fn () => Signalement::factory()->create(['photo' => 'prive/signalements/x_10x10.webp'])],
    'message' => ['messages.show', fn () => Message::factory()->create()],
    'appareil' => ['profile.devices.confirm', fn () => KnownDevice::factory()->create()],
]);

test('le propriétaire ouvre sa propre ressource', function (string $route, Closure $creer) {
    $ressource = $creer();

    $this->actingAs($ressource->user)
        ->get(route($route, $ressource))
        ->assertOk();
})->with([
    'démarche' => ['demarches.show', fn () => Demarche::factory()->create()],
    'rendez-vous' => ['appointments.show', fn () => RendezVous::factory()->create()],
    'remontée' => ['concerns.show', fn () => Remontee::factory()->create()],
    'signalement' => ['signalements.show', fn () => Signalement::factory()->create()],
]);

test('un autre habitant reçoit 403 sur la notification de quelqu’un d’autre', function () {
    $proprietaire = User::factory()->create();
    $proprietaire->notify(new Avis('Votre démarche avance'));
    $notification = $proprietaire->notifications()->firstOrFail();

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('notifications.open', $notification->id))
        ->assertForbidden();

    expect($notification->fresh()->read_at)->toBeNull();
});
