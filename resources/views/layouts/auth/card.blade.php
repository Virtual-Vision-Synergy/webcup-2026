<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['allege' => \App\Support\ModeAllege::actif()])>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-neutral-100 antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <a href="#contenu" class="sr-only z-[60] rounded-sm bg-cyan px-4 py-2 text-on-cyan focus:not-sr-only focus:fixed focus:start-4 focus:top-4">{{ __('Aller au contenu') }}</a>
        <x-tn.bandeau-annonces />
        <div class="bg-muted flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-9 w-9 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                    </span>

                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>

                <x-tn.langue class="justify-center" />

                <main id="contenu" tabindex="-1" class="flex flex-col gap-6">
                    <div class="rounded-xl border bg-white dark:bg-stone-950 dark:border-stone-800 text-stone-800 shadow-xs">
                        <div class="px-10 py-8">{{ $slot }}</div>
                    </div>
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
