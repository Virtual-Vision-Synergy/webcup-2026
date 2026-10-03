<?php

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

    // make:feature:routes
});

/*
| Routes publiques en lecture seule (liste et détail), ajoutées par `make:feature --public`.
| Création, modification et suppression restent dans le groupe `auth` ci-dessus.
*/
Route::group([], function () {
    // make:feature:routes-public
});
