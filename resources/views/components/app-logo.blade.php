@props([
    'sidebar' => false,
    'compact' => false,
])

{{-- Logo Terra Nova : anneau + « TERRA NOVA » (Saira élargie) + sous-titre mono. --}}
<a {{ $attributes->class('group flex min-h-11 items-center gap-2.5 rounded-sm text-ink') }}>
    <x-app-logo-icon class="size-[26px] shrink-0" />
    <span @class(['flex flex-col leading-none', 'max-sm:hidden' => $compact, 'in-data-flux-sidebar-collapsed-desktop:hidden' => $sidebar])>
        <span class="font-display text-[0.9375rem] font-semibold tracking-[.16em]" style="font-stretch: 118%">TERRA NOVA</span>
        <span class="mt-1 font-mono text-[0.59375rem] tracking-[.08em] text-ink-2">{{ __('RÉSEAU CIVIQUE OFFICIEL') }}</span>
    </span>
    <span class="sr-only"> · {{ __('accueil') }}</span>
</a>
