@php
    // Emplacement des illustrations officielles HD : déposer public/images/hero/ciel-nuit.webp (et ciel-jour.webp).
    // Sans fichier, le ciel en CSS sert de secours.
    $illustrationNuit = file_exists(public_path('images/hero/ciel-nuit.webp')) ? asset('images/hero/ciel-nuit.webp') : null;
    $illustrationJour = file_exists(public_path('images/hero/ciel-jour.webp')) ? asset('images/hero/ciel-jour.webp') : null;
@endphp
<!DOCTYPE html>
<html lang="fr">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-night text-ink antialiased">
        <a href="#contenu" class="sr-only z-[60] rounded-sm bg-cyan px-4 py-2 text-on-cyan focus:not-sr-only focus:fixed focus:start-4 focus:top-4">Aller au contenu</a>
        <div class="grid min-h-dvh lg:grid-cols-2">
            {{-- Côté visuel : ciel, planète, grille --}}
            <div class="tn-sky relative hidden overflow-hidden lg:flex lg:flex-col lg:p-10">
                @if ($illustrationNuit)
                    <img src="{{ $illustrationNuit }}" alt="" class="absolute inset-0 hidden size-full object-cover dark:block" fetchpriority="high">
                @endif
                @if ($illustrationJour)
                    <img src="{{ $illustrationJour }}" alt="" class="absolute inset-0 size-full object-cover dark:hidden">
                @endif
                <div class="tn-planet -top-24 -right-24 size-[420px]" aria-hidden="true"></div>
                <div class="tn-grid" aria-hidden="true"></div>

                <x-app-logo href="{{ route('home') }}" class="relative z-10" />

                <div class="relative z-10 mt-auto max-w-md">
                    <x-tn.section-label>Mairie de Nova Terra</x-tn.section-label>
                    <p class="tn-h2 mt-3 text-ink">La ville, en direct, au bout des doigts.</p>
                    <p class="mt-3 text-ink-2">Vos démarches, les actualités du Haut Conseil et vos services municipaux, réunis sur le réseau civique officiel.</p>
                </div>
            </div>

            {{-- Côté formulaire --}}
            <div class="relative flex flex-col px-4 py-6 sm:px-8 lg:p-10">
                <div class="flex items-center justify-between lg:justify-end">
                    <x-app-logo href="{{ route('home') }}" class="lg:hidden" />
                    <x-tn.theme-toggle />
                </div>

                <main id="contenu" tabindex="-1" class="flex flex-1 outline-none items-center justify-center py-8">
                    <x-tn.panel class="w-full max-w-[420px]" padding="p-6 sm:p-8">
                        <div class="flex flex-col gap-6">
                            {{ $slot }}
                        </div>
                    </x-tn.panel>
                </main>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
