{{--
    Gabarit de l'espace connecté Terra Nova.
    Desktop (≥ lg) : rail latéral Flux + header vitré. Mobile : header minimal, barre d'onglets en bas, menu en feuille.
    Les rubriques ajoutées par `make:feature` (marqueur ci-dessous) apparaissent dans le rail ET dans le menu mobile.
--}}
@section('tn-feature-nav')
                    <flux:sidebar.item icon="exclamation-triangle" :href="route('signalements.index')" :current="request()->routeIs('signalements.*')" wire:navigate>
                        {{ __('Signalements') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="bus" :href="route('transports.index')" :current="request()->routeIs('transports.*')" wire:navigate>
                        {{ __('Transports') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="calendar-days" :href="route('appointments.index')" :current="request()->routeIs('appointments.*')" wire:navigate>
                        {{ __('Mes rendez-vous') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('mes-demandes.index')" :current="request()->routeIs('mes-demandes.*')" wire:navigate>
                        Mes demandes
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="heart" :href="route('urgences.index')" :current="request()->routeIs('urgences.*')" wire:navigate>
                        Urgences / Santé
                    </flux:sidebar.item>
                    @can('parOuCommencer', \App\Models\Onboarding::class)
                        <flux:sidebar.item icon="sparkles" :href="route('onboarding.par-ou-commencer')" :current="request()->routeIs('onboarding.par-ou-commencer')" wire:navigate>
                            Par où commencer ?
                        </flux:sidebar.item>
                    @endcan

                    <flux:sidebar.item icon="chat-bubble-left-ellipsis" :href="route('concerns.index')" :current="request()->routeIs('concerns.*')" wire:navigate>
                        Mes remontées
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="shield-check" :href="route('privacy.show')" :current="request()->routeIs('privacy.show')" wire:navigate>
                        Vos données
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="building-office-2" :href="route('projets.index')" :current="request()->routeIs('projets.*')" wire:navigate>
                        Projets de la ville
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="chat-bubble-bottom-center-text" :href="route('avis.index')" :current="request()->routeIs('avis.*')" wire:navigate>
                        Mes avis
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="building-storefront" :href="route('partners.index')" :current="request()->routeIs('partners.*')" wire:navigate>
                        Partenaires
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="light-bulb" :href="route('ideas.index')" :current="request()->routeIs('ideas.*')" wire:navigate>
                        Boîte à idées
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="chat-bubble-left-right" :href="route('consultations.index')" :current="request()->routeIs('consultations.*')" wire:navigate>
                        Consultations
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="eye" :href="route('accessibility.show')" :current="request()->routeIs('accessibility.show')" wire:navigate>
                        Accessibilité
                    </flux:sidebar.item>

                    {{-- make:feature:nav --}}
@endsection
@php
    // F30 : filet de sécurité si le cron du planificateur ne tourne pas (annonces programmées arrivées à leur début).
    // Avant tout rendu, pour que les deux cloches affichent le même compteur.
    app(\App\Services\NotifierAnnonce::class)->traiterEchuesAuPlusUneFoisParMinute();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['allege' => \App\Support\ModeAllege::actif()])>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-black antialiased" x-data>
        <a href="#contenu" class="sr-only z-[60] rounded-sm bg-cyan px-4 py-2 text-on-cyan focus:not-sr-only focus:fixed focus:start-4 focus:top-4">{{ __('Aller au contenu') }}</a>

        <div
            id="tn-page"
            class="tn-page min-h-screen bg-night text-ink lg:flex"
            x-bind:class="$store.menu?.ouvert && 'tn-page-recule'"
        >
            <flux:sidebar sticky class="border-e max-lg:hidden!">
                <flux:sidebar.header>
                    <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                </flux:sidebar.header>

                <flux:sidebar.nav>
                    <flux:sidebar.group :heading="__('Espace citoyen')" class="grid">
                        <flux:sidebar.item icon="house" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Mon espace') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="landmark" :href="route('services.index')" :current="request()->routeIs('services.*')" wire:navigate>
                            {{ __('Services') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="newspaper" :href="route('actualites.index')" :current="request()->routeIs('actualites.*')" wire:navigate>
                            {{ __('Actualités') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="mail" :href="route('messages.index')" :current="request()->routeIs('messages.*')" wire:navigate>
                            {{ __('Messages') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="file-text" :href="route('demarches.index')" :current="request()->routeIs('demarches.*')" wire:navigate>
                            {{ __('Mes démarches') }}
                        </flux:sidebar.item>

                        @yield('tn-feature-nav')
                    </flux:sidebar.group>

                    @can('viewAgentSpace')
                        <flux:sidebar.group :heading="__('Espace agent')" class="grid">
                            <flux:sidebar.item icon="briefcase" :href="route('agent.tableau-de-bord')" data-test="agent-space-link">
                                {{ __('Espace agent') }}
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @endcan
                </flux:sidebar.nav>

                <flux:spacer />

                <flux:sidebar.nav>
                    <livewire:cloche-notifications />
                </flux:sidebar.nav>

                <x-desktop-user-menu :name="auth()->user()->name" />
            </flux:sidebar>

            <div class="flex min-w-0 flex-1 flex-col pb-[calc(4rem+env(safe-area-inset-bottom))] lg:pb-0">
                <header class="tn-glass sticky top-0 z-30 border-b">
                    <div class="flex h-16 items-center gap-3 px-4 lg:h-[72px] lg:px-8">
                        <x-app-logo href="{{ route('dashboard') }}" class="lg:hidden" wire:navigate />

                        <div class="ms-auto flex items-center gap-2">
                            <x-tn.api-status class="max-sm:hidden" />
                            <x-tn.cloche />
                            <x-tn.langue class="max-lg:hidden" />
                            <x-tn.contrast-toggle class="max-lg:hidden" />
                            <x-tn.text-size class="max-lg:hidden" />
                            <x-tn.theme-toggle class="max-lg:hidden" />
                            <x-tn.mode-allege class="max-lg:hidden" />
                            <a href="{{ route('profile.edit') }}" class="flex size-11 items-center justify-center lg:hidden" wire:navigate>
                                <flux:avatar size="sm" :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                                <span class="sr-only">{{ __('Mon compte :') }} {{ auth()->user()->name }}</span>
                            </a>
                        </div>
                    </div>
                </header>

                <x-tn.bandeau-annonces />
                <x-tn.bandeau-consultations />

                <main id="contenu" tabindex="-1" class="flex flex-1 flex-col">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <x-tn.bottom-nav />
        <x-tn.menu-sheet>
            <x-slot:autres>
                <div class="grid">
                    @yield('tn-feature-nav')
                </div>
            </x-slot:autres>
        </x-tn.menu-sheet>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        <x-tn.chargement />

        @fluxScripts
    </body>
</html>
