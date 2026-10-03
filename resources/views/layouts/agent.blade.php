{{--
    Gabarit de l'espace agent (agents et administrateurs).
    Volontairement différent de l'espace citoyen : pas de menu latéral, bandeau émeraude, badge « Espace agent ».
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-100 dark:bg-zinc-950">
        <flux:header container class="border-b-4 border-emerald-500 bg-emerald-950 text-white">
            <a href="{{ route('agent.index') }}" class="flex items-center gap-2 font-semibold">
                <flux:icon.briefcase class="size-6 text-emerald-400" />
                <span class="max-sm:hidden">Nova Terra</span>
                <flux:badge color="emerald" size="sm">Espace agent</flux:badge>
            </a>

            <flux:navbar class="ms-6 max-md:hidden">
                <flux:navbar.item icon="inbox-stack" :href="route('agent.index')" :current="request()->routeIs('agent.index')" class="!text-emerald-50">
                    Demandes Nova Terra
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <flux:button :href="route('dashboard')" variant="ghost" size="sm" icon="arrow-uturn-left" class="!text-emerald-50 max-md:hidden">
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
                            <flux:badge size="sm" color="emerald" class="mt-1 w-fit">{{ auth()->user()->role->label }}</flux:badge>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.item icon="inbox-stack" :href="route('agent.index')" class="md:hidden">Demandes Nova Terra</flux:menu.item>
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
