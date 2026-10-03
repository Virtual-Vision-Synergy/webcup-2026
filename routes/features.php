<?php

use App\Http\Controllers\KnownDeviceController;
use App\Http\Controllers\NotificationController;
use App\Models\Onboarding;
use Illuminate\Support\Facades\Route;

/*
| Routes des fonctionnalités métier.
| Les blocs sont ajoutés automatiquement par `php artisan make:feature`.
| Tout ce qui est dans ce groupe exige d'être connecté.
*/

Route::middleware(['auth'])->group(function () {
    Route::livewire('services', 'pages::services.index')->name('services.index');
    Route::livewire('services/create', 'pages::services.form')->name('services.create');
    Route::livewire('services/{service}', 'pages::services.show')->name('services.show');
    Route::livewire('services/{service}/edit', 'pages::services.form')->name('services.edit');

    Route::livewire('actualites', 'pages::actualites.index')->name('actualites.index');
    Route::livewire('actualites/create', 'pages::actualites.form')->name('actualites.create');
    Route::livewire('actualites/{actualite}', 'pages::actualites.show')->name('actualites.show');
    Route::livewire('actualites/{actualite}/edit', 'pages::actualites.form')->name('actualites.edit');

    Route::livewire('messages', 'pages::messages.index')->name('messages.index');
    Route::livewire('messages/create', 'pages::messages.form')->name('messages.create');
    Route::livewire('messages/{message}', 'pages::messages.show')->name('messages.show');
    Route::livewire('messages/{message}/edit', 'pages::messages.form')->name('messages.edit');

    Route::livewire('roles', 'pages::roles.index')->name('roles.index');
    Route::livewire('roles/create', 'pages::roles.form')->name('roles.create');
    Route::livewire('roles/{role}', 'pages::roles.show')->name('roles.show');
    Route::livewire('roles/{role}/edit', 'pages::roles.form')->name('roles.edit');

    Route::livewire('demarches', 'pages::demarches.index')->name('demarches.index');
    Route::livewire('demarches/historique', 'pages::demarches.historique')->name('demarches.historique');
    // F56 : récapitulatif imprimable / CSV, limité aux demandes de l'utilisateur connecté.
    Route::livewire('demarches/recapitulatif', 'pages::demarches.recapitulatif')->name('demarches.recapitulatif');
    Route::livewire('demarches/create', 'pages::demarches.form')->name('demarches.create');
    Route::livewire('demarches/{demarche}', 'pages::demarches.show')->name('demarches.show');
    Route::livewire('demarches/{demarche}/edit', 'pages::demarches.form')->name('demarches.edit');

    Route::livewire('signalements', 'pages::signalements.index')->name('signalements.index');
    Route::livewire('signalements/create', 'pages::signalements.form')->name('signalements.create');
    Route::livewire('signalements/{signalement}', 'pages::signalements.show')->name('signalements.show');
    Route::livewire('signalements/{signalement}/edit', 'pages::signalements.form')->name('signalements.edit');

    // Parcours de prise en main des nouveaux habitants (D12) : toujours celui de l'utilisateur connecté.
    Route::livewire('bienvenue', 'pages::onboarding.index')
        ->middleware('can:view,'.Onboarding::class)
        ->name('onboarding.show');

    Route::livewire('transports', 'pages::transports.index')->name('transports.index');
    Route::livewire('transports/create', 'pages::transports.form')->name('transports.create');
    Route::livewire('transports/{ligneTransport}', 'pages::transports.show')->name('transports.show');
    Route::livewire('transports/{ligneTransport}/edit', 'pages::transports.form')->name('transports.edit');

    // F39 : prise de rendez-vous avec un agent (droits dans RendezVousPolicy ; agenda agent dans routes/agent.php).
    Route::livewire('rendez-vous', 'pages::rendez-vous.index')->name('appointments.index');
    Route::livewire('rendez-vous/prendre', 'pages::rendez-vous.form')->name('appointments.create');
    Route::livewire('rendez-vous/{rendezVous}', 'pages::rendez-vous.show')->name('appointments.show');

    // F30 : notifications de l'utilisateur connecté (droits dans DatabaseNotificationPolicy : 403 pour celle d'un autre).
    Route::livewire('notifications', 'pages::notifications.index')->name('notifications.index');
    Route::get('notifications/compteur', [NotificationController::class, 'compteur'])->name('notifications.count');
    Route::post('notifications/tout-lire', [NotificationController::class, 'toutLire'])->name('notifications.read-all');
    Route::post('notifications/{notification}/lire', [NotificationController::class, 'lire'])->whereUuid('notification')->name('notifications.read');
    Route::get('notifications/{notification}/ouvrir', [NotificationController::class, 'ouvrir'])->whereUuid('notification')->name('notifications.open');

    // F67 : création et mise à jour des projets de la ville (ProjetPolicy : agents et admins).
    Route::livewire('projets/create', 'pages::projets.form')->name('projets.create');
    Route::livewire('projets/{projet}/edit', 'pages::projets.form')->name('projets.edit');

    // F66 : avis de l'habitant connecté sur les projets (donner son avis : action sur la fiche projet, ProjetPolicy::donnerAvis).
    Route::livewire('mes-avis', 'pages::avis.index')->name('avis.index');

    // F51 : remontées d'inquiétudes sur les données (RemonteePolicy : l'auteur seul, sinon 403 ; traitement dans routes/agent.php).
    Route::livewire('mes-remontees', 'pages::remontees.index')->name('concerns.index');
    Route::livewire('mes-remontees/nouvelle', 'pages::remontees.form')->name('concerns.create');
    Route::livewire('mes-remontees/{remontee}', 'pages::remontees.show')->name('concerns.show');
    Route::livewire('mes-remontees/{remontee}/accuse-reception', 'pages::remontees.accuse-reception')->name('concerns.received');

    // F54 : mes appareils et connexions récentes ; « Ce n'était pas moi » (droits dans KnownDevicePolicy : 403 pour l'appareil d'un autre).
    Route::livewire('profil/appareils', 'pages::profile.devices')->name('profile.devices.index');
    Route::get('profil/appareils/{knownDevice}/pas-moi', [KnownDeviceController::class, 'confirm'])->name('profile.devices.confirm');
    Route::post('profil/appareils/{knownDevice}/pas-moi', [KnownDeviceController::class, 'notMe'])->name('profile.devices.not-me');

    // make:feature:routes
});

/*
| F54 : lien « Ce n'était pas moi » de l'e-mail d'alerte. Route publique DÉCIDÉE (la personne peut ne plus avoir
| accès à sa session) : URL signée 24 h liée à l'appareil et à son propriétaire ; GET = confirmation, POST = action.
*/
Route::middleware(['signed', 'throttle:10,1'])->group(function () {
    Route::get('appareils/{knownDevice}/signaler', [KnownDeviceController::class, 'showSigned'])->name('profile.devices.report');
    Route::post('appareils/{knownDevice}/signaler', [KnownDeviceController::class, 'reportSigned'])->name('profile.devices.report.store');
});

/*
| Routes publiques en lecture seule (liste et détail), ajoutées par `make:feature --public`.
| Création, modification et suppression restent dans le groupe `auth` ci-dessus.
*/
Route::group([], function () {
    // F29 : page d'une alerte en cours, consultable sans compte (lien partageable) ; 404 hors période (AnnoncePolicy::view).
    Route::livewire('alertes/{annonce}', 'pages::alertes.show')->name('alertes.show');

    // F67 : projets de la ville consultables sans compte (décision assumée : information citoyenne).
    Route::livewire('projets', 'pages::projets.index')->name('projets.index');
    Route::livewire('projets/{projet}', 'pages::projets.show')->name('projets.show');

    // F46 : urgences et santé, consultable sans compte (numéros d'urgence, hôpitaux) ; lecture seule, aucune action.
    Route::livewire('urgences', 'pages::urgences.index')->name('urgences.index');

    // make:feature:routes-public
});
