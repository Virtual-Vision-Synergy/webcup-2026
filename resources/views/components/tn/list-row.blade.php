@props([
    'icon' => 'activity',
    'href' => null,
    'stack' => false,
])

@php $tag = $href ? 'a' : 'div'; @endphp

{{-- Ligne de liste : icône 36px | contenu | à-côté. `stack` : l'à-côté passe sous le contenu sur mobile. --}}
<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->class([
    'grid items-center gap-x-3 gap-y-1.5 border-t border-line py-3.5',
    'grid-cols-[36px_minmax(0,1fr)_auto]' => ! $stack,
    'grid-cols-[36px_minmax(0,1fr)] sm:grid-cols-[36px_minmax(0,1fr)_auto]' => $stack,
    'group rounded-xs hover:bg-cyan/[.03]' => $href,
]) }}>
    <span @class(['flex size-9 items-center justify-center rounded-sm border border-cyan/18 bg-cyan/8 text-cyan', 'self-start sm:self-center' => $stack]) aria-hidden="true">
        <flux:icon :name="$icon" class="size-[18px]" />
    </span>
    <span class="min-w-0">{{ $slot }}</span>
    @isset($aside)
        <span @class(['flex items-center', 'col-start-2 sm:col-start-auto' => $stack])>{{ $aside }}</span>
    @endisset
</{{ $tag }}>
