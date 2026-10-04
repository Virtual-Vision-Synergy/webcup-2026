@props([
    'id',
    'title' => null,
])

{{-- Aide courte au bon moment (F35). Masquable une par une ou toutes d'un coup (Paramètres → Apparence). --}}
<aside
    x-data
    x-show="$store.aides.visible(@js($id))"
    x-cloak
    role="note"
    aria-label="{{ __('Aide') }}"
    {{ $attributes->class('flex items-start gap-3 rounded-md border border-cyan/30 bg-cyan/8 px-4 py-3') }}
>
    <flux:icon name="information-circle" class="mt-0.5 size-5 shrink-0 text-cyan" aria-hidden="true" />
    <div class="min-w-0 flex-1 text-sm text-ink-2">
        <span class="sr-only">{{ __('Aide :') }}</span>
        @if ($title)
            <p class="font-medium text-ink">{{ __($title) }}</p>
        @endif
        <div>{{ $slot }}</div>
        <button type="button" x-on:click="$store.aides.basculer(false)" class="mt-1 inline-flex min-h-11 items-center text-xs text-cyan hover:underline sm:min-h-0">{{ __('Ne plus afficher les aides') }}</button>
    </div>
    <button
        type="button"
        x-on:click="$store.aides.masquer(@js($id))"
        class="-m-2 inline-flex size-11 shrink-0 items-center justify-center rounded-sm text-ink-2 hover:text-ink focus-visible:outline-2 focus-visible:outline-cyan"
        aria-label="{{ __('Masquer cette aide') }}"
    >
        <flux:icon name="x-mark" class="size-4" aria-hidden="true" />
    </button>
</aside>
