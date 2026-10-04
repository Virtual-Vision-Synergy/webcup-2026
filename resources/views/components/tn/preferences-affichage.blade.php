{{-- D20 : langue et aides d'affichage regroupées dans un menu, pour garder l'en-tête lisible sur une ligne. --}}
<div
    x-data="{ ouvert: false }"
    x-on:keydown.escape.window="ouvert = false"
    x-on:click.outside="ouvert = false"
    {{ $attributes->class('relative') }}
>
    <button
        type="button"
        x-on:click="ouvert = ! ouvert"
        x-bind:aria-expanded="ouvert ? 'true' : 'false'"
        aria-controls="preferences-affichage"
        title="{{ __('Langue et affichage') }}"
        class="inline-flex h-11 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-sm px-2.5 text-ink-2 transition-colors hover:bg-cyan/8 hover:text-ink"
    >
        <flux:icon name="adjustments-horizontal" class="size-5" />
        <span class="font-mono text-xs uppercase" aria-hidden="true">{{ app()->getLocale() }}</span>
        <span class="sr-only">{{ __('Langue et affichage') }}</span>
        <flux:icon name="chevron-down" class="size-4" />
    </button>

    <div
        id="preferences-affichage"
        x-show="ouvert"
        x-cloak
        x-transition.opacity
        class="absolute end-0 top-full z-50 mt-2 w-72 space-y-1 rounded-md border border-line bg-surface p-2 shadow-lg"
    >
        <div class="flex min-h-12 items-center justify-between gap-3 px-2">
            <span class="text-sm font-medium text-ink">{{ __('Langue') }}</span>
            <x-tn.langue class="-me-1" />
        </div>
        <div class="flex min-h-12 items-center justify-between gap-3 px-2">
            <span class="text-sm font-medium text-ink">{{ __('Taille du texte') }}</span>
            <x-tn.text-size />
        </div>
        <div class="flex min-h-12 items-center justify-between gap-3 px-2">
            <span class="text-sm font-medium text-ink">{{ __('Contraste élevé') }}</span>
            <x-tn.contrast-toggle class="-me-1" />
        </div>
        <div class="flex min-h-12 items-center justify-between gap-3 px-2">
            <span class="text-sm font-medium text-ink">{{ __('Apparence') }}</span>
            <x-tn.theme-toggle class="-me-1" />
        </div>
        <div class="flex min-h-12 items-center justify-between gap-3 px-2">
            <span class="text-sm font-medium text-ink">{{ __('Mode allégé') }}</span>
            <x-tn.mode-allege class="-me-1" />
        </div>
        <a href="{{ route('accessibility.show') }}" class="flex min-h-11 items-center gap-2 rounded-sm px-2 text-sm text-cyan hover:underline">
            <flux:icon name="eye" class="size-4" />{{ __('Accessibilité : toutes les aides') }}
        </a>
    </div>
</div>
