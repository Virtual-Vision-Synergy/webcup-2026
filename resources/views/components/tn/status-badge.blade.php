@props([
    'etat' => 'info',
    'live' => false,
])

@php
    // La couleur indique un ÉTAT : vert = normal, ambre = perturbé, magenta = alerte, cyan = information.
    $couleur = match ($etat) {
        'normal' => 'text-green border-green/35 bg-green/8',
        'perturbe' => 'text-amber border-amber/35 bg-amber/8',
        'alerte' => 'text-magenta border-magenta/35 bg-magenta/8',
        default => 'text-cyan border-cyan/35 bg-cyan/8',
    };
@endphp

<span {{ $attributes->class(['inline-flex shrink-0 items-center gap-1.5 rounded-xs border px-2 py-0.5 font-mono text-[10.5px] font-medium uppercase leading-5 tracking-[.06em]', $couleur]) }}>
    @if ($live)
        <x-tn.live-dot />
    @endif
    {{ $slot }}
</span>
