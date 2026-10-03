{{--
    Gabarit du site public Terra Nova (accueil, pages publiques pour les invités).
    Header vitré en haut ; sur mobile, barre d'onglets en bas + menu en feuille (pas de burger).
--}}
@props([
    'title' => null,
    'description' => null,
    'fluid' => false,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title, 'description' => $description])
    </head>
    <body class="min-h-screen bg-black antialiased" x-data>
        <a href="#contenu" class="sr-only z-[60] rounded-sm bg-cyan px-4 py-2 text-on-cyan focus:not-sr-only focus:fixed focus:start-4 focus:top-4">{{ __('Aller au contenu') }}</a>

        <div
            id="tn-page"
            class="tn-page flex min-h-screen flex-col bg-night pb-[calc(4rem+env(safe-area-inset-bottom))] text-ink lg:pb-0"
            x-bind:class="$store.menu?.ouvert && 'tn-page-recule'"
        >
            <x-tn.site-header />
            <x-tn.bandeau-annonces />

            <main id="contenu" tabindex="-1" @class(['flex-1', 'mx-auto w-full max-w-7xl px-4 py-8 lg:px-8' => ! $fluid])>
                {{ $slot }}
            </main>

            <footer class="border-t border-line">
                <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-sm text-ink-2 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                    <p>{{ config('app.name') }} · Mairie de Nova Terra · 24h by Webcup 2026</p>
                    <nav aria-label="{{ __('Informations') }}" class="flex flex-wrap gap-x-4 gap-y-1">
                        <a href="{{ route('privacy.show') }}" class="text-cyan hover:underline">{{ __('Vos données') }}</a>
                        <a href="{{ route('accessibility.show') }}" class="text-cyan hover:underline">{{ __('Accessibilité') }}</a>
                    </nav>
                    <p class="font-mono text-xs uppercase tracking-[.06em]">Virtual Vision Synergie</p>
                </div>
            </footer>
        </div>

        <x-tn.bottom-nav />
        <x-tn.menu-sheet />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
