<?php

/*
| Navigation Terra Nova : une seule liste lue par le header, la barre d'onglets mobile et le menu.
| Une entrée n'est affichée que si sa route existe (Route::has) : ajouter une rubrique = une ligne ici.
| Les rubriques générées par `make:feature` apparaissent aussi via le marqueur de layouts/app/sidebar.blade.php.
*/

return [
    // Les 4 rubriques principales (numérotées 01–04 sur l'accueil et en grille dans le menu mobile).
    'rubriques' => [
        ['label' => 'Services', 'route' => 'services.index', 'match' => 'services.*', 'icon' => 'landmark', 'texte' => 'Horaires, adresses et contacts des services municipaux.'],
        ['label' => 'Actualités', 'route' => 'actualites.index', 'match' => 'actualites.*', 'icon' => 'newspaper', 'texte' => 'Les annonces du Haut Conseil et la vie de la ville.'],
        ['label' => 'Démarches', 'route' => 'demarches.index', 'match' => 'demarches.*', 'icon' => 'file-text', 'texte' => 'Déposez une demande et suivez son traitement.'],
        ['label' => 'Contact', 'route' => 'messages.index', 'match' => 'messages.*', 'icon' => 'mail', 'texte' => 'Écrivez à vos services et retrouvez vos échanges.'],
    ],

    // Liens du header desktop (dans l'ordre). Carte et Transports apparaîtront dès que leurs routes existeront.
    'header' => [
        ['label' => 'Accueil', 'route' => 'home', 'match' => 'home'],
        ['label' => 'Carte', 'route' => 'carte.index', 'match' => 'carte.*'],
        ['label' => 'Transports', 'route' => 'transports.index', 'match' => 'transports.*'],
        ['label' => 'Actualités', 'route' => 'actualites.index', 'match' => 'actualites.*'],
        ['label' => 'Services', 'route' => 'services.index', 'match' => 'services.*'],
        ['label' => 'Projets', 'route' => 'projets.index', 'match' => 'projets.*'],
    ],

    // Onglet central de la barre mobile (action principale).
    'action' => ['label' => 'Déposer', 'route' => 'demarches.create', 'icon' => 'plus'],
];
