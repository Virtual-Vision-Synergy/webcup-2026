<?php

use App\Http\Controllers\KnownDeviceController;
use App\Http\Controllers\NotificationController;
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
    Route::livewire('demarches/historique', 'pages::demarches.historique')->name('demarches.historique');
    // F56 : récapitulatif imprimable / CSV, limité aux demandes de l'utilisateur connecté.
    Route::livewire('demarches/recapitulatif', 'pages::demarches.recapitulatif')->name('demarches.recapitulatif');
    Route::livewire('demarches/create', 'pages::demarches.form')->name('demarches.create');
    Route::livewire('demarches/{demarche}', 'pages::demarches.show')->name('demarches.show');
    // F83 : accusé de réception imprimable (auteur, personnel du service, admin : DemarchePolicy::voirAccuse).
    Route::livewire('demarches/{demarche}/accuse', 'pages::demarches.accuse')->name('demarches.accuse');
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

    // F39 : prise de rendez-vous avec un agent (droits dans RendezVousPolicy ; agenda agent dans routes/agent.php).
    Route::livewire('rendez-vous', 'pages::rendez-vous.index')->name('appointments.index');
    Route::livewire('rendez-vous/prendre', 'pages::rendez-vous.form')->name('appointments.create');
    Route::livewire('rendez-vous/{rendezVous}', 'pages::rendez-vous.show')->name('appointments.show');

    // F30 : notifications de l'utilisateur connecté (droits dans DatabaseNotificationPolicy : 403 pour celle d'un autre).
    Route::livewire('notifications', 'pages::notifications.index')->name('notifications.index');
    Route::get('notifications/compteur', [NotificationController::class, 'compteur'])->name('notifications.count');
    Route::post('notifications/tout-lire', [NotificationController::class, 'toutLire'])->name('notifications.read-all');
    Route::post('notifications/{notification}/lire', [NotificationController::class, 'lire'])->whereUuid('notification')->name('notifications.read');
    Route::get('notifications/{notification}/ouvrir', [NotificationController::class, 'ouvrir'])->whereUuid('notification')->name('notifications.open');

    // D11 : « Mes demandes » de l'habitant connecté (requête filtrée sur l'auteur ; détail : SignalementPolicy::viewOwn → 403 pour autrui).
    Route::livewire('mes-demandes', 'pages::mes-demandes.index')->name('mes-demandes.index');
    Route::livewire('mes-demandes/{signalement}', 'pages::mes-demandes.show')->name('mes-demandes.show');

    // F67 : création et mise à jour des projets de la ville (ProjetPolicy : agents et admins).
    Route::livewire('projets/create', 'pages::projets.form')->name('projets.create');
    Route::livewire('projets/{projet}/edit', 'pages::projets.form')->name('projets.edit');

    // F66 : avis de l'habitant connecté sur les projets (donner son avis : action sur la fiche projet, ProjetPolicy::donnerAvis).
    Route::livewire('mes-avis', 'pages::avis.index')->name('avis.index');

    // F51 : remontées d'inquiétudes sur les données (RemonteePolicy : l'auteur seul, sinon 403 ; traitement dans routes/agent.php).
    Route::livewire('mes-remontees', 'pages::remontees.index')->name('concerns.index');
    Route::livewire('mes-remontees/nouvelle', 'pages::remontees.form')->name('concerns.create');
    Route::livewire('mes-remontees/{remontee}', 'pages::remontees.show')->name('concerns.show');
    Route::livewire('mes-remontees/{remontee}/accuse-reception', 'pages::remontees.accuse-reception')->name('concerns.received');

    // F54 : mes appareils et connexions récentes ; « Ce n'était pas moi » (droits dans KnownDevicePolicy : 403 pour l'appareil d'un autre).
    Route::livewire('profil/appareils', 'pages::profile.devices')->name('profile.devices.index');
    Route::get('profil/appareils/{knownDevice}/pas-moi', [KnownDeviceController::class, 'confirm'])->name('profile.devices.confirm');
    Route::post('profil/appareils/{knownDevice}/pas-moi', [KnownDeviceController::class, 'notMe'])->name('profile.devices.not-me');

    // F55 : export des données personnelles de l'utilisateur connecté (aucun identifiant dans l'URL ; UserPolicy::exportPersonalData).
    Route::livewire('profil/mes-donnees', 'pages::profile.mes-donnees')->name('profile.data');

    // F72 : « Par où commencer ? » — services recommandés selon la situation de l'habitant (OnboardingPolicy::parOuCommencer).
    Route::livewire('par-ou-commencer', 'pages::onboarding.par-ou-commencer')
        ->middleware('can:parOuCommencer,'.Onboarding::class)
        ->name('onboarding.par-ou-commencer');

    // F68 : boîte à idées — proposer, accusé de réception (auteur seul : IdeaPolicy::viewOwn) et « Mes idées ».
    Route::livewire('idees/proposer', 'pages::ideas.form')->name('ideas.create');
    Route::livewire('idees/mes-idees', 'pages::ideas.mine')->name('ideas.mine');
    Route::livewire('idees/{idea:reference}/confirmation', 'pages::ideas.confirmation')
        ->where('idea', 'IDE-\d{4}-\d{6}')
        ->name('ideas.received');

    // F65 : consultations des habitants (ConsultationPolicy : création et décision agents/admins, réponse unique de l'habitant concerné).
    Route::livewire('consultations', 'pages::consultations.index')->name('consultations.index');
    Route::livewire('consultations/create', 'pages::consultations.form')->name('consultations.create');
    Route::livewire('consultations/{consultation}', 'pages::consultations.show')->name('consultations.show');

    // F76 : avis des habitants sur les services (ServiceReviewPolicy : un avis par habitant et par service, modifiable
    // par son auteur tant qu'il n'est pas masqué). service_id vient toujours de la route, jamais du formulaire.
    Route::livewire('services/{service}/avis', 'pages::service-reviews.show')->name('services.reviews.index');
    Route::livewire('services/{service}/avis/donner', 'pages::service-reviews.form')->name('services.reviews.edit');
    Route::livewire('mes-avis/services', 'pages::service-reviews.index')->name('services.reviews.mine');

    // F92 : « Je ne sais pas à qui m'adresser » — orientation vers le bon service à partir d'une description libre (moteur D10).
    Route::livewire('orientation', 'pages::orientation.index')->name('orientation.index');

    // make:feature:routes
});

/*
| F54 : lien « Ce n'était pas moi » de l'e-mail d'alerte. Route publique DÉCIDÉE (la personne peut ne plus avoir
| accès à sa session) : URL signée 24 h liée à l'appareil et à son propriétaire ; GET = confirmation, POST = action.
*/
Route::middleware(['signed', 'throttle:10,1'])->group(function () {
    Route::get('appareils/{knownDevice}/signaler', [KnownDeviceController::class, 'showSigned'])->name('profile.devices.report');
    Route::post('appareils/{knownDevice}/signaler', [KnownDeviceController::class, 'reportSigned'])->name('profile.devices.report.store');
});

/*
| Routes publiques en lecture seule (liste et détail), ajoutées par `make:feature --public`.
| Création, modification et suppression restent dans le groupe `auth` ci-dessus.
*/
Route::group([], function () {
    // F29 : page d'une alerte en cours, consultable sans compte (lien partageable) ; 404 hors période (AnnoncePolicy::view).
    Route::livewire('alertes/{annonce}', 'pages::alertes.show')->name('alertes.show');

    // F67 : projets de la ville consultables sans compte (décision assumée : information citoyenne).
    Route::livewire('projets', 'pages::projets.index')->name('projets.index');
    Route::livewire('projets/{projet}', 'pages::projets.show')->name('projets.show');

    // F46 : urgences et santé, consultable sans compte (numéros d'urgence, hôpitaux) ; lecture seule, aucune action.
    Route::livewire('urgences', 'pages::urgences.index')->name('urgences.index');

    // F74 : partenaires publiés consultables sans compte (horaires, adresse, carte) ; gestion dans routes/agent.php.
    Route::livewire('partenaires', 'pages::partners.index')->name('partners.index');
    Route::livewire('partenaires/{partner:slug}', 'pages::partners.show')->name('partners.show');

    // F68 : idées publiées consultables sans compte (décision assumée) ; soutenir exige d'être connecté.
    // Idée masquée par la modération : 404 sauf pour son auteur et le personnel (IdeaPolicy::view).
    Route::livewire('idees', 'pages::ideas.index')->name('ideas.index');
    Route::livewire('idees/{idea:reference}', 'pages::ideas.show')->where('idea', 'IDE-\d{4}-\d{6}')->name('ideas.show');

    // D13 : lexique des mots administratifs, consultable sans compte (informations générales, aucune donnée personnelle, lecture seule).
    Route::livewire('lexique', 'pages::lexique.index')->name('lexique');

    // F31 : canicule, consultable sans compte (information de santé : alertes par quartier, conseils écrits, numéros utiles).
    Route::livewire('canicule', 'pages::canicule.index')->name('canicule');

    // make:feature:routes-public
});
