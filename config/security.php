<?php

/*
| F37 : protection contre les tentatives de connexion abusives.
| Seuls les ÉCHECS de connexion sont comptés (voir App\Auth\LoginThrottle).
*/
return [

    'login' => [
        // Couple e-mail + IP : 5 échecs → blocage de 15 minutes.
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('LOGIN_DECAY_MINUTES', 15),

        // IP seule, plus large : une même source qui essaie plusieurs comptes.
        'ip_max_attempts' => (int) env('LOGIN_IP_MAX_ATTEMPTS', 20),
        'ip_decay_minutes' => (int) env('LOGIN_IP_DECAY_MINUTES', 10),

        // Afficher les essais restants à partir de ce nombre.
        'warn_remaining' => 2,

        // Au plus une alerte « tentatives suspectes » par compte sur cette durée.
        'notify_every_minutes' => 60,

        // Durée de conservation du journal des tentatives (Prunable).
        'retention_days' => 30,
    ],

    // Inscription et « mot de passe oublié » : requêtes par minute et par IP.
    'sensitive_routes_per_minute' => 5,

    /*
    | F81 : protection des formulaires contre les robots (sans CAPTCHA).
    | Champ piège invisible + jeton d'horodatage chiffré (délai minimal de remplissage) + limites par compte et par IP.
    */
    'formulaires' => [
        // Durée de validité du jeton d'horodatage (au-delà : « rechargez la page »).
        'jeton_valide_minutes' => 120,

        // Délai minimal entre l'affichage et l'envoi, en secondes (un humain met toujours plus longtemps).
        'delai_minimal' => [
            'connexion' => 1,
            'inscription' => 3,
            'contact' => 3,
            'signalement' => 3,
        ],

        // Envois par minute (contact et signalement) : par compte, et plus large par IP.
        'limites' => [
            'contact' => ['compte' => 5, 'ip' => 15],
            'signalement' => ['compte' => 10, 'ip' => 30],
        ],
    ],

    /*
    | F85 : détection d'activité inhabituelle. Aucun blocage sur un seul signal faible :
    | chaque seuil crée un événement (et prévient l'utilisateur) ; seul un cumul de signaux forts verrouille.
    */
    'surveillance' => [
        // Rafale : requêtes d'un même compte sur une minute (un usage normal, polls compris, reste loin en dessous).
        'rafale_requetes' => (int) env('SECURITE_RAFALE_REQUETES', 120),

        // Accès refusés (403) d'un même compte sur 10 minutes.
        'refus_max' => (int) env('SECURITE_REFUS_MAX', 5),
        'refus_minutes' => 10,

        // Créations / modifications / suppressions d'un même compte sur 5 minutes.
        'modifications_max' => (int) env('SECURITE_MODIFICATIONS_MAX', 30),
        'modifications_minutes' => 5,

        // Score de suspicion sur 24 h (info = 0, moyen = 1, élevé = 3) : à partir de ce score, le compte est « suspect ».
        'score_suspect' => 2,

        // Verrouillage automatique (jamais pour un admin) quand le score sur 1 h atteint ce seuil.
        'score_verrouillage_auto' => (int) env('SECURITE_SCORE_VERROUILLAGE', 6),
        'verrouillage_auto_minutes' => 15,
    ],

    /*
    | F82 : envoi en double des formulaires. Le même contenu, envoyé par le même habitant sur le même formulaire
    | dans cette fenêtre, n'est pas enregistré une seconde fois (en plus du jeton unique par affichage).
    */
    'doublons' => [
        'fenetre_minutes' => (int) env('DOUBLONS_FENETRE_MINUTES', 5),
    ],

];
