<?php

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

    Route::livewire('alertes-canicule', 'pages::alertes-canicule.index')->name('alertes-canicule.index');
    Route::livewire('alertes-canicule/create', 'pages::alertes-canicule.form')->name('alertes-canicule.create');
    Route::livewire('alertes-canicule/{alerte_canicule}/edit', 'pages::alertes-canicule.form')->name('alertes-canicule.edit');

    // make:feature:routes
});

/*
| Routes publiques en lecture seule (liste et détail), ajoutées par `make:feature --public`.
| Création, modification et suppression restent dans le groupe `auth` ci-dessus.
*/
Route::group([], function () {
    // make:feature:routes-public
});
