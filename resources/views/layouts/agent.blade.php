{{--
    Gabarit de l'espace agent (agents et administrateurs).
    Volontairement différent de l'espace citoyen : pas de menu latéral, bandeau émeraude, badge « Espace agent ».
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-night text-ink antialiased">
        {{-- Bandeau « poste agent » : même système Terra Nova, repérable par l'intitulé et le trait cyan. --}}
        <flux:header container class="tn-glass sticky top-0 z-40 h-[72px]! border-b border-t-2 border-t-cyan">
            <a href="{{ route('agent.index') }}" class="flex items-center gap-3" aria-label="{{ __('Espace agent : demandes Nova Terra') }}">
                <x-app-logo-icon class="size-[26px] shrink-0" />
                <span class="tn-display text-[0.9375rem] font-semibold tracking-[.16em] max-sm:hidden" style="font-stretch: 118%">TERRA NOVA</span>
                <x-tn.status-badge etat="info">{{ __('Espace agent') }}</x-tn.status-badge>
            </a>

            <flux:navbar class="ms-6 max-md:hidden">
                <flux:navbar.item icon="inbox-stack" :href="route('agent.index')" :current="request()->routeIs('agent.index')">
                    {{ __('Demandes Nova Terra') }}
                </flux:navbar.item>
                <flux:navbar.item icon="megaphone" :href="route('agent.annonces.index')" :current="request()->routeIs('agent.annonces.*')">
                    {{ __('Messages généraux') }}
                </flux:navbar.item>
                <flux:navbar.item icon="users" :href="route('agent.citizens.index')" :current="request()->routeIs('agent.citizens.*')">
                    {{ __('Comptes citoyens') }}
                </flux:navbar.item>
                <flux:navbar.item icon="clipboard-document-list" :href="route('agent.demandes')" :current="request()->routeIs('agent.demandes')">
                    {{ __('Demandes des habitants') }}
                </flux:navbar.item>
                <flux:navbar.item icon="document-text" :href="route('agent.audit.index')" :current="request()->routeIs('agent.audit.*')">
                    {{ __('Journal') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <x-tn.contrast-toggle class="max-md:hidden" />
            <x-tn.theme-toggle class="max-md:hidden" />
            <flux:button :href="route('dashboard')" variant="ghost" size="sm" icon="arrow-uturn-left" class="max-md:hidden">
                {{ __('Espace citoyen') }}
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

                    <flux:menu.item icon="inbox-stack" :href="route('agent.index')" class="md:hidden">{{ __('Demandes Nova Terra') }}</flux:menu.item>
                    <flux:menu.item icon="users" :href="route('agent.citizens.index')" class="md:hidden">{{ __('Comptes citoyens') }}</flux:menu.item>
                    <flux:menu.item icon="megaphone" :href="route('agent.annonces.index')" class="md:hidden">{{ __('Messages généraux') }}</flux:menu.item>
                    <flux:menu.item icon="clipboard-document-list" :href="route('agent.demandes')" class="md:hidden">{{ __('Demandes des habitants') }}</flux:menu.item>
                    <flux:menu.item icon="document-text" :href="route('agent.audit.index')" class="md:hidden">{{ __('Journal') }}</flux:menu.item>
                    <flux:menu.item icon="arrow-uturn-left" :href="route('dashboard')">{{ __('Retour à l\'espace citoyen') }}</flux:menu.item>
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

        <flux:main container>
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
