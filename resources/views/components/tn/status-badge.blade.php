@props([
    'etat' => 'info',
    'live' => false,
    'icon' => null,
])

@php
    // F43 : la couleur indique un ÉTAT, mais n'est jamais seule : chaque état a aussi sa forme d'icône
    // (coche, triangle, croix, « i ») en plus du libellé texte du badge. Vert = normal, ambre = perturbé,
    // magenta = alerte, cyan = information.
    $style = match ($etat) {
        'normal' => ['classes' => 'text-green border-green/35 bg-green/8', 'icone' => 'check-circle'],
        'perturbe' => ['classes' => 'text-amber border-amber/35 bg-amber/8', 'icone' => 'exclamation-triangle'],
        'alerte' => ['classes' => 'text-magenta border-magenta/35 bg-magenta/8', 'icone' => 'x-circle'],
        default => ['classes' => 'text-cyan border-cyan/35 bg-cyan/8', 'icone' => 'information-circle'],
    };
@endphp

<span {{ $attributes->class(['inline-flex max-w-full shrink-0 items-center gap-1.5 rounded-xs border px-2 py-0.5 font-mono text-[0.65625rem] font-medium uppercase leading-5 tracking-[.06em]', $style['classes']]) }} data-etat="{{ $etat }}">
    @if ($live)
        <x-tn.live-dot />
    @endif
    <flux:icon :name="$icon ?? $style['icone']" variant="micro" class="size-3.5 shrink-0" aria-hidden="true" data-etat-icone />
    {{ $slot }}
</span>
