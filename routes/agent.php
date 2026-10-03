<?php

use Illuminate\Support\Facades\Route;

/*
| Espace agent : réservé aux agents et administrateurs (Gate « viewAgentSpace »).
| Chaque page vérifie aussi Gate::authorize('viewAgentSpace') elle-même.
*/
Route::middleware(['auth', 'can:viewAgentSpace'])->prefix('agent')->name('agent.')->group(function () {
    Route::livewire('/', 'pages::agent.index')->name('index');
    Route::livewire('demandes', 'pages::agent.demandes')->name('demandes');
});
