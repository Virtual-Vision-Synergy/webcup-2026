@php
    // Emplacement des illustrations officielles HD : déposer public/images/hero/ciel-nuit.webp (et ciel-jour.webp).
    // Sans fichier, le ciel en CSS sert de secours.
    // F59 : en « Mode allégé », aucune illustration décorative n'est téléchargée.
    $allege = \App\Support\ModeAllege::actif();
    $illustrationNuit = ! $allege && file_exists(public_path('images/hero/ciel-nuit.webp')) ? asset('images/hero/ciel-nuit.webp') : null;
    $illustrationJour = ! $allege && file_exists(public_path('images/hero/ciel-jour.webp')) ? asset('images/hero/ciel-jour.webp') : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['allege' => \App\Support\ModeAllege::actif()])>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-night text-ink antialiased">
        <a href="#contenu" class="sr-only z-[60] rounded-sm bg-cyan px-4 py-2 text-on-cyan focus:not-sr-only focus:fixed focus:start-4 focus:top-4">{{ __('Aller au contenu') }}</a>
        <x-tn.bandeau-annonces />
        <div class="grid min-h-dvh grid-cols-1 lg:grid-cols-2">
            {{-- Côté visuel : ciel, planète, grille --}}
            <div class="tn-sky relative hidden overflow-hidden lg:flex lg:flex-col lg:p-10">
                @if ($illustrationNuit)
                    {{-- F60 : loading="lazy" = seule l'illustration du thème affiché est téléchargée. --}}
                    <img src="{{ $illustrationNuit }}" alt="" class="absolute inset-0 hidden size-full object-cover dark:block" loading="lazy" decoding="async">
                @endif
                @if ($illustrationJour)
                    <img src="{{ $illustrationJour }}" alt="" class="absolute inset-0 size-full object-cover dark:hidden" loading="lazy" decoding="async">
                @endif
                <div class="tn-planet -top-24 -right-24 size-[420px]" aria-hidden="true"></div>
                <div class="tn-grid" aria-hidden="true"></div>

                <x-app-logo href="{{ route('home') }}" class="relative z-10" />

                <div class="relative z-10 mt-auto max-w-md">
                    <x-tn.section-label>{{ __('Mairie de Nova Terra') }}</x-tn.section-label>
                    <p class="tn-h2 mt-3 text-ink">{{ __('La ville, en direct, au bout des doigts.') }}</p>
                    <p class="mt-3 text-ink-2">{{ __('Vos démarches, les actualités du Haut Conseil et vos services municipaux, réunis sur le réseau civique officiel.') }}</p>
                </div>
            </div>

            {{-- Côté formulaire --}}
            <div class="relative flex flex-col px-4 py-6 sm:px-8 lg:p-10">
                <div class="flex flex-wrap items-center justify-between gap-2 lg:justify-end">
                    <x-app-logo href="{{ route('home') }}" class="lg:hidden" />
                    <div class="flex flex-wrap items-center gap-1"><x-tn.langue /><x-tn.contrast-toggle /><x-tn.text-size /><x-tn.theme-toggle /></div>
                </div>

                <main id="contenu" tabindex="-1" class="flex flex-1 items-center justify-center py-8">
                    <x-tn.panel class="w-full max-w-[420px]" padding="p-6 sm:p-8">
                        <div class="flex flex-col gap-6">
                            {{ $slot }}
                        </div>
                    </x-tn.panel>
                </main>

                <p class="text-center text-sm text-ink-2">
                    <a href="{{ route('accessibility.show') }}" class="text-cyan hover:underline">{{ __('Accessibilité : les aides disponibles') }}</a>
                </p>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        <x-tn.chargement />

        @fluxScripts
    </body>
</html>
