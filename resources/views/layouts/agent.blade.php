{{--
    Gabarit de l'espace agent (agents et administrateurs).
    Volontairement différent de l'espace citoyen : pas de menu latéral, bandeau émeraude, badge « Espace agent ».
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['allege' => \App\Support\ModeAllege::actif()])>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-night text-ink antialiased">
        <a href="#contenu" class="sr-only z-[60] rounded-sm bg-cyan px-4 py-2 text-on-cyan focus:not-sr-only focus:fixed focus:start-4 focus:top-4">{{ __('Aller au contenu') }}</a>

        {{-- Bandeau « poste agent » : même système Terra Nova, repérable par l'intitulé et le trait cyan. --}}
        <flux:header container class="tn-glass sticky top-0 z-40 h-[72px]! border-b border-t-2 border-t-cyan">
            <a href="{{ route('agent.tableau-de-bord') }}" class="flex items-center gap-3" aria-label="Espace agent : tableau de bord">
                <x-app-logo-icon class="size-[26px] shrink-0" />
                <span class="tn-display text-[0.9375rem] font-semibold tracking-[.16em] max-sm:hidden">TERRA NOVA</span>
                <x-tn.status-badge etat="info">Espace agent</x-tn.status-badge>
            </a>

            <flux:navbar class="ms-6 max-md:hidden">
                <flux:navbar.item icon="chart-bar" :href="route('agent.tableau-de-bord')" :current="request()->routeIs('agent.tableau-de-bord')">
                    Tableau de bord
                </flux:navbar.item>
                <flux:navbar.item icon="inbox-stack" :href="route('agent.index')" :current="request()->routeIs('agent.index')">
                    Demandes Nova Terra
                </flux:navbar.item>
                @php($annoncesEnCours = \App\Models\Annonce::query()->active()->count())
                <flux:navbar.item icon="megaphone" :href="route('agent.annonces.index')" :current="request()->routeIs('agent.annonces.*')" :badge="$annoncesEnCours ?: null" :aria-label="'Messages généraux, '.$annoncesEnCours.' en cours'">
                    Messages généraux
                </flux:navbar.item>
                <flux:navbar.item icon="users" :href="route('agent.citizens.index')" :current="request()->routeIs('agent.citizens.*')">
                    Comptes citoyens
                </flux:navbar.item>
                <flux:navbar.item icon="clipboard-document-list" :href="route('agent.demandes')" :current="request()->routeIs('agent.demandes')">
                    Demandes des habitants
                </flux:navbar.item>
                <flux:navbar.item icon="squares-2x2" :href="route('agent.signalements.similaires')" :current="request()->routeIs('agent.signalements.similaires')">
                    Similaires
                </flux:navbar.item>
                @php($remonteesEnAttente = \App\Models\Remontee::query()->enAttente()->count())
                <flux:navbar.item icon="chat-bubble-left-ellipsis" :href="route('agent.concerns.index')" :current="request()->routeIs('agent.concerns.*')" :badge="$remonteesEnAttente ?: null" :aria-label="'Remontées sur les données, '.$remonteesEnAttente.' en attente'">
                    Remontées données
                </flux:navbar.item>
                <flux:navbar.item icon="light-bulb" :href="route('agent.ideas.index')" :current="request()->routeIs('agent.ideas.*')">
                    Boîte à idées
                </flux:navbar.item>
                <flux:navbar.item icon="calendar-days" :href="route('agent.appointments.index')" :current="request()->routeIs('agent.appointments.*')">
                    Rendez-vous
                </flux:navbar.item>
                <flux:navbar.item icon="wrench-screwdriver" :href="route('agent.services.index')" :current="request()->routeIs('agent.services.*')">
                    Services
                </flux:navbar.item>
                <flux:navbar.item icon="building-office-2" :href="route('projets.index')">
                    Projets
                </flux:navbar.item>
                <flux:navbar.item icon="building-storefront" :href="route('agent.partners.index')" :current="request()->routeIs('agent.partners.*')">
                    Partenaires
                </flux:navbar.item>
                <flux:navbar.item icon="shield-check" :href="route('agent.security.index')" :current="request()->routeIs('agent.security.*')">
                    Sécurité
                </flux:navbar.item>
                <flux:navbar.item icon="document-text" :href="route('agent.audit.index')" :current="request()->routeIs('agent.audit.*')">
                    Journal
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <x-tn.contrast-toggle class="max-md:hidden" />
            <x-tn.theme-toggle class="max-md:hidden" />
            <flux:button :href="route('dashboard')" variant="ghost" size="sm" icon="arrow-uturn-left" class="max-md:hidden">
                Espace citoyen
            </flux:button>
            <flux:dropdown position="bottom" align="end">
                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" class="ms-2" />

                <flux:menu>
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                            <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            <flux:badge size="sm" class="mt-1 w-fit">{{ auth()->user()->role->label }}</flux:badge>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.item icon="chart-bar" :href="route('agent.tableau-de-bord')" class="md:hidden">Tableau de bord</flux:menu.item>
                    <flux:menu.item icon="inbox-stack" :href="route('agent.index')" class="md:hidden">Demandes Nova Terra</flux:menu.item>
                    <flux:menu.item icon="users" :href="route('agent.citizens.index')" class="md:hidden">Comptes citoyens</flux:menu.item>
                    <flux:menu.item icon="megaphone" :href="route('agent.annonces.index')" class="md:hidden">Messages généraux</flux:menu.item>
                    <flux:menu.item icon="clipboard-document-list" :href="route('agent.demandes')" class="md:hidden">Demandes des habitants</flux:menu.item>
                    <flux:menu.item icon="squares-2x2" :href="route('agent.signalements.similaires')" class="md:hidden">Demandes similaires</flux:menu.item>
                    <flux:menu.item icon="chat-bubble-left-ellipsis" :href="route('agent.concerns.index')" class="md:hidden">Remontées sur les données</flux:menu.item>
                    <flux:menu.item icon="light-bulb" :href="route('agent.ideas.index')" class="md:hidden">Boîte à idées</flux:menu.item>
                    <flux:menu.item icon="calendar-days" :href="route('agent.appointments.index')" class="md:hidden">Rendez-vous du jour</flux:menu.item>
                    <flux:menu.item icon="wrench-screwdriver" :href="route('agent.services.index')" class="md:hidden">Disponibilité des services</flux:menu.item>
                    <flux:menu.item icon="building-office-2" :href="route('projets.index')" class="md:hidden">Projets de la ville</flux:menu.item>
                    <flux:menu.item icon="building-storefront" :href="route('agent.partners.index')" class="md:hidden">Partenaires</flux:menu.item>
                    <flux:menu.item icon="document-text" :href="route('agent.audit.index')" class="md:hidden">Journal</flux:menu.item>
                    <flux:menu.item icon="shield-check" :href="route('agent.security.index')" class="md:hidden">Sécurité des connexions</flux:menu.item>
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
        </flux:header>

        <x-tn.bandeau-annonces />

        {{-- F44 : pas de <flux:main> (grille Flux) : le bandeau d'alerte y devenait une colonne étroite à côté du contenu.
             min-w-0 : un tableau large défile dans son conteneur au lieu d'élargir la page. --}}
        <main id="contenu" tabindex="-1" class="mx-auto w-full min-w-0 max-w-7xl px-4 py-6 sm:px-6 lg:p-8">
            {{ $slot }}
        </main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        <x-tn.chargement />

        @fluxScripts
    </body>
</html>
