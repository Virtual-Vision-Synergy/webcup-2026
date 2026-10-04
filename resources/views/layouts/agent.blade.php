{{--
    Gabarit de l'espace agent (agents et administrateurs).
    Même mise en page que l'espace citoyen (rail latéral ≥ lg, header vitré) ; repérable par le badge « Espace agent » et le trait cyan.
    Mobile : toutes les rubriques sont dans le menu du compte (en haut à droite), qui défile s'il est trop long.
--}}
@php
    $annoncesEnCours = \App\Models\Annonce::query()->active()->count();
    $remonteesEnAttente = \App\Models\Remontee::query()->enAttente()->count();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['allege' => \App\Support\ModeAllege::actif()])>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-black antialiased" x-data>
        <a href="#contenu" class="sr-only z-[60] rounded-sm bg-cyan px-4 py-2 text-on-cyan focus:not-sr-only focus:fixed focus:start-4 focus:top-4">{{ __('Aller au contenu') }}</a>

        <div class="min-h-screen bg-night text-ink lg:flex">
            <flux:sidebar sticky class="border-e max-lg:hidden!">
                <flux:sidebar.header class="flex-col items-start! gap-2">
                    <x-app-logo :sidebar="true" href="{{ route('agent.tableau-de-bord') }}" aria-label="Espace agent : tableau de bord" />
                    <x-tn.status-badge etat="info">Espace agent</x-tn.status-badge>
                </flux:sidebar.header>

                <flux:sidebar.nav>
                    <flux:sidebar.group heading="Espace agent" class="grid">
                        <flux:sidebar.item icon="chart-bar" :href="route('agent.tableau-de-bord')" :current="request()->routeIs('agent.tableau-de-bord')">
                            Tableau de bord
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="inbox-stack" :href="route('agent.index')" :current="request()->routeIs('agent.index')">
                            Demandes Nova Terra
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('agent.demandes')" :current="request()->routeIs('agent.demandes')">
                            Demandes des habitants
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="squares-2x2" :href="route('agent.signalements.similaires')" :current="request()->routeIs('agent.signalements.similaires')">
                            Demandes similaires
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="megaphone" :href="route('agent.annonces.index')" :current="request()->routeIs('agent.annonces.*')" :badge="$annoncesEnCours ?: null" :aria-label="'Messages généraux, '.$annoncesEnCours.' en cours'">
                            Messages généraux
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('agent.citizens.index')" :current="request()->routeIs('agent.citizens.*')">
                            Comptes citoyens
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="calendar-days" :href="route('agent.appointments.index')" :current="request()->routeIs('agent.appointments.*')">
                            Rendez-vous
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="wrench-screwdriver" :href="route('agent.services.index')" :current="request()->routeIs('agent.services.*')">
                            Services
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group heading="Retours des habitants" class="grid">
                        <flux:sidebar.item icon="chat-bubble-left-ellipsis" :href="route('agent.concerns.index')" :current="request()->routeIs('agent.concerns.*')" :badge="$remonteesEnAttente ?: null" :aria-label="'Remontées sur les données, '.$remonteesEnAttente.' en attente'">
                            Remontées données
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="light-bulb" :href="route('agent.ideas.index')" :current="request()->routeIs('agent.ideas.*')">
                            Boîte à idées
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="star" :href="route('agent.reviews.index')" :current="request()->routeIs('agent.reviews.*')">
                            Avis des habitants
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="chat-bubble-left-right" :href="route('consultations.index')" :current="request()->routeIs('consultations.*')">
                            Consultations
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="building-office-2" :href="route('projets.index')" :current="request()->routeIs('projets.*', 'agent.projets.*')">
                            Projets
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="building-storefront" :href="route('agent.partners.index')" :current="request()->routeIs('agent.partners.*')">
                            Partenaires
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group heading="Contrôle" class="grid">
                        <flux:sidebar.item icon="shield-check" :href="route('agent.security.index')" :current="request()->routeIs('agent.security.*')">
                            Sécurité
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="document-text" :href="route('agent.audit.index')" :current="request()->routeIs('agent.audit.*', 'agent.history.*')">
                            Journal
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                </flux:sidebar.nav>

                <flux:spacer />

                <flux:sidebar.nav>
                    <flux:sidebar.item icon="arrow-uturn-left" :href="route('dashboard')">
                        Retour à l'espace citoyen
                    </flux:sidebar.item>
                </flux:sidebar.nav>

                <x-desktop-user-menu :name="auth()->user()->name" />
            </flux:sidebar>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="tn-glass sticky top-0 z-30 border-b border-t-2 border-t-cyan">
                    <div class="flex h-16 items-center gap-3 px-4 lg:h-[72px] lg:px-8">
                        <a href="{{ route('agent.tableau-de-bord') }}" class="flex min-w-0 items-center gap-3 lg:hidden" aria-label="Espace agent : tableau de bord">
                            <x-app-logo-icon class="size-[26px] shrink-0" />
                            <span class="tn-display text-[0.9375rem] font-semibold tracking-[.16em] max-sm:hidden">TERRA NOVA</span>
                        </a>
                        <x-tn.status-badge etat="info" class="lg:hidden">Espace agent</x-tn.status-badge>

                        <div class="ms-auto flex items-center gap-2">
                            <x-tn.contrast-toggle class="max-md:hidden" />
                            <x-tn.theme-toggle class="max-md:hidden" />

                            {{-- Mobile / tablette : toutes les rubriques de l'espace agent (le rail latéral est masqué). --}}
                            <flux:dropdown position="bottom" align="end" class="lg:hidden">
                                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" aria-label="Menu de l'espace agent" />

                                <flux:menu class="max-h-[75dvh] overflow-y-auto">
                                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                        <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                                        <div class="grid flex-1 text-start text-sm leading-tight">
                                            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                            <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                            <flux:badge size="sm" class="mt-1 w-fit">{{ auth()->user()->role->label }}</flux:badge>
                                        </div>
                                    </div>

                                    <flux:menu.separator />

                                    <flux:menu.item icon="chart-bar" :href="route('agent.tableau-de-bord')">Tableau de bord</flux:menu.item>
                                    <flux:menu.item icon="inbox-stack" :href="route('agent.index')">Demandes Nova Terra</flux:menu.item>
                                    <flux:menu.item icon="clipboard-document-list" :href="route('agent.demandes')">Demandes des habitants</flux:menu.item>
                                    <flux:menu.item icon="squares-2x2" :href="route('agent.signalements.similaires')">Demandes similaires</flux:menu.item>
                                    <flux:menu.item icon="megaphone" :href="route('agent.annonces.index')">Messages généraux</flux:menu.item>
                                    <flux:menu.item icon="users" :href="route('agent.citizens.index')">Comptes citoyens</flux:menu.item>
                                    <flux:menu.item icon="calendar-days" :href="route('agent.appointments.index')">Rendez-vous du jour</flux:menu.item>
                                    <flux:menu.item icon="wrench-screwdriver" :href="route('agent.services.index')">Disponibilité des services</flux:menu.item>
                                    <flux:menu.item icon="chat-bubble-left-ellipsis" :href="route('agent.concerns.index')">Remontées sur les données</flux:menu.item>
                                    <flux:menu.item icon="light-bulb" :href="route('agent.ideas.index')">Boîte à idées</flux:menu.item>
                                    <flux:menu.item icon="star" :href="route('agent.reviews.index')">Avis des habitants</flux:menu.item>
                                    <flux:menu.item icon="chat-bubble-left-right" :href="route('consultations.index')">Consultations</flux:menu.item>
                                    <flux:menu.item icon="building-office-2" :href="route('projets.index')">Projets de la ville</flux:menu.item>
                                    <flux:menu.item icon="building-storefront" :href="route('agent.partners.index')">Partenaires</flux:menu.item>
                                    <flux:menu.item icon="shield-check" :href="route('agent.security.index')">Sécurité des connexions</flux:menu.item>
                                    <flux:menu.item icon="document-text" :href="route('agent.audit.index')">Journal</flux:menu.item>

                                    <flux:menu.separator />

                                    <flux:menu.item icon="arrow-uturn-left" :href="route('dashboard')">Retour à l'espace citoyen</flux:menu.item>
                                    <flux:menu.item icon="cog" :href="route('profile.edit')">{{ __('Settings') }}</flux:menu.item>

                                    <flux:menu.separator />

                                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                                        @csrf
                                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">
                                            {{ __('Log out') }}
                                        </flux:menu.item>
                                    </form>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>
                </header>

                {{-- Bandeaux hors de toute grille, frères directs de <main> : pleine largeur de la colonne de contenu. --}}
                <x-tn.bandeau-annonces />

                {{-- min-w-0 : un tableau large défile dans son conteneur au lieu d'élargir la page. --}}
                <main id="contenu" tabindex="-1" class="mx-auto w-full min-w-0 max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        <x-tn.chargement />
        <x-tn.etat-reseau />

        @fluxScripts
    </body>
</html>
