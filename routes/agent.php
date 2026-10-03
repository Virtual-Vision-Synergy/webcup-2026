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

    // F34 : comptes citoyens (droits fins dans UserPolicy : administerAccounts, viewAccount, deactivate, reactivate).
    Route::livewire('/citoyens', 'pages::agent.citizens.index')->name('citizens.index');
    Route::livewire('/citoyens/{user}', 'pages::agent.citizens.show')->name('citizens.show');
    Route::livewire('demandes', 'pages::agent.demandes')->name('demandes');

    // F37 : journal des tentatives de connexion (LoginAttemptPolicy : viewAny agent/admin, unlock admin).
    Route::livewire('securite/connexions', 'pages::agent.security.index')->name('security.index');

    // F47 : journal d'audit en LECTURE SEULE (aucune route de création, modification ni suppression).
    Route::livewire('journal', 'pages::agent.audit.index')->name('audit.index');
    Route::livewire('journal/{auditLog}', 'pages::agent.audit.show')->name('audit.show');
});
