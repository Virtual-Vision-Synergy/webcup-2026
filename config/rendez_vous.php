<?php

/*
| Prise de rendez-vous avec un agent (F39).
| Les heures sont stockées en UTC (app.timezone) et affichées dans le fuseau de Nova Terra.
*/

return [

    /** Fuseau d'affichage des créneaux. */
    'fuseau' => env('RENDEZ_VOUS_FUSEAU', 'Indian/Antananarivo'),

    /** Mention affichée après chaque horaire. */
    'libelle_fuseau' => 'heure de Nova Terra',

    /** Plages d'ouverture aux rendez-vous, en heure locale. */
    'plages' => [
        ['09:00', '12:00'],
        ['14:00', '16:30'],
    ],

    /** Jours ouvrés (1 = lundi … 7 = dimanche, ISO-8601). */
    'jours_ouvres' => [1, 2, 3, 4, 5],

    /** Nombre de jours ouvrés générés par défaut par appointments:generate-slots. */
    'jours' => 14,

    /** Délai minimum entre maintenant et le début d'un créneau réservable. */
    'delai_reservation_minutes' => 120,

    /** Délai minimum avant le début du rendez-vous pour pouvoir l'annuler. */
    'delai_annulation_minutes' => 60,

];
