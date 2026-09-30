<?php

use Illuminate\Support\Facades\Route;

/*
| Routes des fonctionnalités métier.
| Les blocs sont ajoutés automatiquement par `php artisan make:feature`.
| Tout ce qui est dans ce groupe exige d'être connecté.
*/

Route::middleware(['auth'])->group(function () {
    Route::livewire('signalements', 'pages::signalements.index')->name('signalements.index');
    Route::livewire('signalements/create', 'pages::signalements.form')->name('signalements.create');
    Route::livewire('signalements/{signalement}', 'pages::signalements.show')->name('signalements.show');
    Route::livewire('signalements/{signalement}/edit', 'pages::signalements.form')->name('signalements.edit');

    // make:feature:routes
});
