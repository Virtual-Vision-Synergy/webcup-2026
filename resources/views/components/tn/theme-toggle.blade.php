{{-- Bascule clair / sombre (44px). Le choix complet Clair / Sombre / Système est dans Paramètres > Apparence. --}}
<button
    type="button"
    x-data
    x-on:click="$flux.appearance = $flux.dark ? 'light' : 'dark'"
    x-bind:aria-pressed="$flux.dark ? 'true' : 'false'"
    aria-label="{{ __('Thème sombre') }}"
    title="{{ __('Thème sombre') }}"
    {{ $attributes->class('inline-flex size-11 shrink-0 items-center justify-center rounded-sm text-ink-2 transition-colors hover:bg-cyan/8 hover:text-ink') }}
>
    <flux:icon.sun class="hidden size-5 dark:block" />
    <flux:icon.moon class="size-5 dark:hidden" />
</button>
