@props([
    'icon' => 'activity',
    'href' => null,
])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->class(['grid grid-cols-[36px_1fr_auto] items-center gap-3 border-t border-line py-3.5', 'group rounded-xs hover:bg-cyan/[.03]' => $href]) }}>
    <span class="flex size-9 items-center justify-center rounded-sm border border-cyan/18 bg-cyan/8 text-cyan" aria-hidden="true">
        <flux:icon :name="$icon" class="size-[18px]" />
    </span>
    <span class="min-w-0">{{ $slot }}</span>
    @isset($aside)
        <span class="flex items-center">{{ $aside }}</span>
    @endisset
</{{ $tag }}>
