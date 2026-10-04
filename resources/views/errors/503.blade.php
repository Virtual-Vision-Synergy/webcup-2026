{{--
    F94 : page d'erreur 503 volontairement autonome (ni gabarit, ni base, ni session, ni JS) : elle s'affiche
    même quand la base est coupée et renvoie vers la page « Infos essentielles » (fichier statique).
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Service momentanément indisponible · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 36rem; margin: 0 auto; padding: 4rem 1rem; text-align: center; }
        a { color: #0e7490; font-weight: 600; }
        @media (prefers-color-scheme: dark) { a { color: #5fd4e8; } }
    </style>
</head>
<body>
<main>
    <p>Erreur 503</p>
    <h1>Service momentanément indisponible</h1>
    <p>La plateforme est en maintenance ou surchargée. Réessayez dans quelques minutes ; vos démarches déjà déposées restent enregistrées.</p>
    <p data-test="lien-infos-essentielles"><a href="/infos-essentielles">Infos essentielles : consignes, numéros d’urgence et coordonnées de la mairie →</a></p>
    <p><a href="/">Réessayer depuis l’accueil</a></p>
</main>
</body>
</html>
