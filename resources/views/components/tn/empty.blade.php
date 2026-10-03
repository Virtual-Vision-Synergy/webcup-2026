@props([
    'icon' => 'radio',
    'title',
    'text' => null,
])

{{-- État vide : message + action (slot). --}}
<div {{ $attributes->class('flex flex-col items-center rounded-md border border-dashed border-line px-6 py-14 text-center') }}>
    <span class="flex size-14 items-center justify-center rounded-full border border-cyan/25 bg-cyan/8 text-cyan" aria-hidden="true">
        <flux:icon :name="$icon" class="size-6" />
    </span>
    <p class="tn-display mt-4 text-lg font-semibold text-ink">{{ is_string($title) ? __($title) : $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-sm text-ink-2">{{ is_string($text) ? __($text) : $text }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
