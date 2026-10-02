{{--
    Gabarit des pages publiques (générées par `make:feature --public`).
    Connecté : le gabarit habituel avec menu latéral. Invité : un simple en-tête avec liens de connexion.
--}}
@auth
    <x-layouts::app :title="$title ?? null">
        {{ $slot }}
    </x-layouts::app>
@else
    <!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
        <head>
            @include('partials.head')
        </head>
        <body class="min-h-screen bg-white dark:bg-zinc-800">
            <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
                <x-app-logo href="{{ route('home') }}" wire:navigate />

                <flux:spacer />

                <flux:button :href="route('login')" variant="ghost" size="sm">Connexion</flux:button>
                @if (Route::has('register'))
                    <flux:button :href="route('register')" variant="primary" size="sm" class="ms-2">Inscription</flux:button>
                @endif
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
@endauth
