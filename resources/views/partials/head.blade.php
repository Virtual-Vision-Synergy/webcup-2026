@php
    $pageTitle = filled($title ?? null) ? __($title).' · Terra Nova' : 'Terra Nova · '.__('Réseau civique officiel');
    $pageDescription = $description ?? __('Plateforme civique de la Mairie de Nova Terra : démarches, actualités du Haut Conseil, services municipaux et contact, au même endroit.');
@endphp
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
<meta name="description" content="{{ $pageDescription }}" />
<meta name="theme-color" content="#050A16" media="(prefers-color-scheme: dark)" />
<meta name="theme-color" content="#F2F8FB" media="(prefers-color-scheme: light)" />
<meta name="color-scheme" content="dark light" />

<title>{{ $pageTitle }}</title>

{{-- F93 : empreinte anonyme de la session : si l'utilisateur change sur cet appareil, pages hors ligne et brouillons sont effacés. --}}
@auth
    <meta name="tn-session" content="{{ substr(hash_hmac('sha256', (string) auth()->id(), (string) config('app.key')), 0, 16) }}" />
@endauth

<link rel="canonical" href="{{ url()->current() }}" />
<meta property="og:type" content="website" />
<meta property="og:locale" content="{{ app()->getLocale() === 'en' ? 'en_US' : 'fr_FR' }}" />
<meta property="og:site_name" content="Terra Nova" />
<meta property="og:title" content="{{ $pageTitle }}" />
<meta property="og:description" content="{{ $pageDescription }}" />
<meta property="og:url" content="{{ url()->current() }}" />

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- F58 : une seule police (IBM Plex Sans), auto-hébergée par Vite au build ; aucun appel à un CDN de polices. --}}
{{-- F62 : en version simple, police du système (aucun fichier de police téléchargé). --}}
@unless (\App\Support\VersionSimple::actif())
    @fonts
@endunless

@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- Thème sombre par défaut : on l'enregistre avant que Flux applique l'apparence (pas de flash). --}}
<script>
    try {
        if (! window.localStorage.getItem('tn.theme-init')) {
            window.localStorage.setItem('tn.theme-init', '1');
            if (! window.localStorage.getItem('flux.appearance')) {
                window.localStorage.setItem('flux.appearance', 'dark');
            }
        }
    } catch (e) {}
</script>
{{-- Contraste élevé : préférence mémorisée, appliquée avant l'affichage (pas de flash). --}}
<script>
    try {
        if (window.localStorage.getItem('tn.contrast') === 'high') {
            document.documentElement.classList.add('hc');
        }
    } catch (e) {}
</script>
{{-- Taille du texte mémorisée (A / A+ / A++) : appliquée avant le rendu pour éviter tout saut de mise en page. --}}
<script>
    try {
        var tnSize = window.localStorage.getItem('tn.text-size');
        if (['md', 'lg', 'xl'].indexOf(tnSize) !== -1) {
            document.documentElement.dataset.textSize = tnSize;
        }
    } catch (e) {}
</script>
@fluxAppearance
