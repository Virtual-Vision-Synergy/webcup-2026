<?php

use Illuminate\Support\Facades\Route;

/*
| Routes des fonctionnalités métier.
| Les blocs sont ajoutés automatiquement par `php artisan make:feature`.
| Tout ce qui est dans ce groupe exige d'être connecté.
*/

Route::middleware(['auth'])->group(function () {
    Route::livewire('services/create', 'pages::services.form')->name('services.create');
    Route::livewire('services/{service}/edit', 'pages::services.form')->name('services.edit');

    Route::livewire('roles', 'pages::roles.index')->name('roles.index');
    Route::livewire('roles/create', 'pages::roles.form')->name('roles.create');
    Route::livewire('roles/{role}', 'pages::roles.show')->name('roles.show');
    Route::livewire('roles/{role}/edit', 'pages::roles.form')->name('roles.edit');

    // make:feature:routes
});

/*
| Routes publiques en lecture seule (liste et détail), ajoutées par `make:feature --public`.
| Création, modification et suppression restent dans le groupe `auth` ci-dessus.
*/
Route::group([], function () {
    Route::livewire('services', 'pages::services.index')->name('services.index');
    Route::livewire('services/{service}', 'pages::services.show')->name('services.show')->where('service', '[a-z0-9-]+');

    // make:feature:routes-public
});
