<?php

use Illuminate\Support\Facades\Route;

/*
| Espace agent : réservé aux agents et administrateurs (Gate « viewAgentSpace »).
| Chaque page vérifie aussi Gate::authorize('viewAgentSpace') elle-même.
*/
Route::middleware(['auth', 'can:viewAgentSpace'])->prefix('agent')->name('agent.')->group(function () {
    Route::livewire('/', 'pages::agent.index')->name('index');

    // Messages généraux diffusés en bandeau à tous les habitants (D18).
    Route::livewire('annonces', 'pages::annonces.index')->name('annonces.index');
    Route::livewire('annonces/create', 'pages::annonces.form')->name('annonces.create');
    Route::livewire('annonces/{annonce}/edit', 'pages::annonces.form')->name('annonces.edit');
});
