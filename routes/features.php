<?php

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

    // make:feature:routes
});

/*
| Routes publiques en lecture seule (liste et détail), ajoutées par `make:feature --public`.
| Création, modification et suppression restent dans le groupe `auth` ci-dessus.
*/
Route::group([], function () {
    // F29 : page d'une alerte en cours, consultable sans compte (lien partageable) ; 404 hors période (AnnoncePolicy::view).
    Route::livewire('alertes/{annonce}', 'pages::alertes.show')->name('alertes.show');

    // F46 : urgences et santé, consultable sans compte (numéros d'urgence, hôpitaux) ; lecture seule, aucune action.
    Route::livewire('urgences', 'pages::urgences.index')->name('urgences.index');

    // make:feature:routes-public
});
