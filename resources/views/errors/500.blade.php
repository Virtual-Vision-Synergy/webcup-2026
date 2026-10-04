{{-- D14 : page 500 autonome (sans base de données, sans session ni Vite) pour s'afficher même quand tout le reste échoue. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Erreur 500') }} · Terra Nova</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; padding: 1rem; }
        main { max-width: 36rem; text-align: center; }
        p.code { font-size: .8rem; letter-spacing: .08em; text-transform: uppercase; color: #94a3b8; }
        h1 { font-size: 1.75rem; margin: .5rem 0 1rem; }
        a { display: inline-block; margin-top: 1.5rem; padding: .6rem 1.2rem; border-radius: .375rem; background: #00687B; color: #fff; text-decoration: none; }
    </style>
</head>
<body>
    <main>
        <p class="code">{{ __('Erreur 500') }}</p>
        <h1>{{ __('Un problème technique est survenu') }}</h1>
        <p>{{ __('Le service rencontre une difficulté momentanée. Réessayez dans quelques instants ; vos démarches déjà envoyées sont bien enregistrées.') }}</p>
        <p data-test="lien-infos-essentielles"><a href="/infos-essentielles">{{ __('Infos essentielles : consignes, numéros d’urgence et coordonnées de la mairie') }} →</a></p>
        <a href="{{ url('/') }}">{{ __('Accueil') }}</a>
    </main>
</body>
</html>
