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

];
