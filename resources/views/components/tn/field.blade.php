@props(['label'])

{{-- Ligne de fiche : intitulé (160px) | valeur. --}}
<div {{ $attributes->class('grid gap-1 border-t border-line py-3.5 first:border-t-0 sm:grid-cols-[160px_minmax(0,1fr)] sm:gap-6') }}>
    <dt class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-ink-2 sm:pt-1">{{ is_string($label) ? __($label) : $label }}</dt>
    <dd class="min-w-0 text-ink">{{ $slot }}</dd>
</div>
