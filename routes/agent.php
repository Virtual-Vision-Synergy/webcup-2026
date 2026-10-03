<?php

use App\Models\AuditLog;
use Illuminate\Support\Facades\Route;

/*
| Espace agent : réservé aux agents et administrateurs (Gate « viewAgentSpace »).
| Chaque page vérifie aussi Gate::authorize('viewAgentSpace') elle-même.
*/
Route::middleware(['auth', 'can:viewAgentSpace'])->prefix('agent')->name('agent.')->group(function () {
    Route::livewire('/', 'pages::agent.index')->name('index');

    // F50 : tableau de bord simplifié (compteurs, activité sur 7 jours, dernières demandes).
    Route::livewire('tableau-de-bord', 'pages::agent.tableau-de-bord')->name('tableau-de-bord');

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

    // F39 : rendez-vous du jour (RendezVousPolicy::viewAgenda).
    Route::livewire('rendez-vous', 'pages::agent.rendez-vous')->name('appointments.index');

    // F47 : journal d'audit en LECTURE SEULE (aucune route de création, modification ni suppression).
    Route::livewire('journal', 'pages::agent.audit.index')->name('audit.index');
    Route::livewire('journal/{auditLog}', 'pages::agent.audit.show')->name('audit.show');

    // F48 : historique d'un élément (lecture seule). {type} passe par la liste blanche AuditLog::HISTORY_TYPES (sinon 404).
    Route::livewire('historique/{type}/{id}', 'pages::agent.audit.history')
        ->whereIn('type', array_keys(AuditLog::HISTORY_TYPES))
        ->whereNumber('id')
        ->name('history.show');
});
