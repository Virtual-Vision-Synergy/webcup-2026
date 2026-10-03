<?php

/*
| F30 : notification des annonces importantes (modèle Annonce, niveaux information / vigilance / alerte / danger).
*/
return [

    // Niveaux qui déclenchent une notification dans la cloche des habitants.
    'niveaux_notifies' => ['alerte', 'danger'],

    // Niveaux qui envoient aussi un e-mail (si l'habitant l'accepte dans son profil) : le plus grave seulement.
    'niveaux_email' => ['danger'],

];
