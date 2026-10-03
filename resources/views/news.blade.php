<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Actualités', 'description' => 'Suivez les actualités de la ville'])
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <header class="border-b border-zinc-200 dark:border-zinc-800">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold" aria-label="{{ config('app.name') }} — accueil">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-accent text-accent-foreground">
                        <x-app-logo-icon class="size-5 fill-current" width="20" height="20" aria-hidden="true" />
                    </span>
                    <span class="truncate">{{ config('app.name') }}</span>
                </a>

                <nav class="flex items-center gap-2" aria-label="Compte">
                    @auth
                        <flux:button :href="route('dashboard')" variant="primary" size="sm">Mon espace</flux:button>
                    @else
                        <flux:button :href="route('login')" variant="ghost" size="sm">Connexion</flux:button>
                        @if (Route::has('register'))
                            <flux:button :href="route('register')" variant="primary" size="sm">Inscription</flux:button>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-16">
            <div class="text-center">
                <h1 class="text-4xl font-bold text-accent-content sm:text-5xl">Actualités</h1>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-zinc-600 dark:text-zinc-300">
                    Cette section est en construction. Revenez bientôt pour suivre les actualités de la ville.
                </p>
                <div class="mt-8">
                    <flux:button :href="route('home')" variant="primary">Retour à l'accueil</flux:button>
                </div>
            </div>
        </main>

        <footer class="mx-auto max-w-5xl px-4 py-8 text-center text-sm text-zinc-600 dark:text-zinc-300">
            {{ config('app.name') }} — 24h by Webcup 2026
        </footer>
    </body>
</html>
