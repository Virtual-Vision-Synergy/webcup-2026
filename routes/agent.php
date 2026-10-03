<?php

use Illuminate\Support\Facades\Route;

/*
| Espace agent : réservé aux agents et administrateurs (Gate « viewAgentSpace »).
| Chaque page vérifie aussi Gate::authorize('viewAgentSpace') elle-même.
*/
Route::middleware(['auth', 'can:viewAgentSpace'])->prefix('agent')->name('agent.')->group(function () {
    Route::livewire('/', 'pages::agent.index')->name('index');

    // F34 : comptes citoyens (droits fins dans UserPolicy : administerAccounts, viewAccount, deactivate, reactivate).
    Route::livewire('/citoyens', 'pages::agent.citizens.index')->name('citizens.index');
    Route::livewire('/citoyens/{user}', 'pages::agent.citizens.show')->name('citizens.show');
    Route::livewire('demandes', 'pages::agent.demandes')->name('demandes');
});
