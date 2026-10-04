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
    // F71 : comptes d'habitants sans e-mail (UserPolicy::createResidentAccounts), déclarés avant /citoyens/{user}.
    Route::livewire('/citoyens/nouveau', 'pages::agent.citizens.create')->name('citizens.create');
    Route::livewire('/citoyens/import', 'pages::agent.citizens.import')->name('citizens.import');
    Route::livewire('/citoyens/{user}', 'pages::agent.citizens.show')->name('citizens.show');
    Route::livewire('demandes', 'pages::agent.demandes')->name('demandes');

    // F75 : signalements similaires regroupés, fusion et traitement par groupe (SignalementPolicy::changerStatut).
    Route::livewire('signalements-similaires', 'pages::agent.signalements-similaires')->name('signalements.similaires');

    // F37 : journal des tentatives de connexion (LoginAttemptPolicy : viewAny agent/admin, unlock admin).
    Route::livewire('securite/connexions', 'pages::agent.security.index')->name('security.index');

    // F39 : rendez-vous du jour (RendezVousPolicy::viewAgenda).
    Route::livewire('rendez-vous', 'pages::agent.rendez-vous')->name('appointments.index');

    // F38 : disponibilité des services (maintenance, incident). Droits dans ServicePolicy::manageAvailability.
    Route::livewire('services', 'pages::agent.services.index')->name('services.index');
    Route::livewire('services/{service}/disponibilite', 'pages::agent.services.disponibilite')->name('services.availability');

    // F47 : journal d'audit en LECTURE SEULE (aucune route de création, modification ni suppression).
    Route::livewire('journal', 'pages::agent.audit.index')->name('audit.index');
    Route::livewire('journal/{auditLog}', 'pages::agent.audit.show')->name('audit.show');

    // F51 : remontées des habitants sur leurs données (RemonteePolicy::traiter dans chaque action).
    Route::livewire('donnees/remontees', 'pages::agent.remontees.index')->name('concerns.index');
    Route::livewire('donnees/remontees/{remontee}', 'pages::agent.remontees.show')->name('concerns.show');

    // F66 : synthèse des avis des habitants sur un projet (ProjetPolicy::voirAvis).
    Route::livewire('projets/{projet}/avis', 'pages::agent.projets.avis')->name('projets.avis');

    // F74 : gestion des partenaires (PartnerPolicy : agents et admins, authorize dans chaque action).
    Route::livewire('partenaires', 'pages::agent.partners.index')->name('partners.index');
    Route::livewire('partenaires/create', 'pages::partners.form')->name('partners.create');
    Route::livewire('partenaires/{partner}/edit', 'pages::partners.form')->name('partners.edit');

    // F68 : boîte à idées — tri par soutiens, état, réponse et masquage (IdeaPolicy dans chaque action).
    Route::livewire('idees', 'pages::agent.ideas.index')->name('ideas.index');
    Route::livewire('idees/{idea:reference}', 'pages::agent.ideas.show')->where('idea', 'IDE-\d{4}-\d{6}')->name('ideas.show');

    // F76 : avis des habitants sur les services couverts (F70) — répondre, masquer, réafficher (ServiceReviewPolicy).
    Route::livewire('avis', 'pages::agent.service-reviews.index')->name('reviews.index');

    // F48 : historique d'un élément (lecture seule). {type} passe par la liste blanche AuditLog::HISTORY_TYPES (sinon 404).
    Route::livewire('historique/{type}/{id}', 'pages::agent.audit.history')
        ->whereIn('type', array_keys(AuditLog::HISTORY_TYPES))
        ->whereNumber('id')
        ->name('history.show');
});
