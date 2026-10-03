@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'flex items-center justify-center gap-1.5 font-medium text-sm text-green']) }} role="status">
        <flux:icon.check-circle variant="mini" class="size-4 shrink-0" aria-hidden="true" />
        <span>{{ $status }}</span>
    </div>
@endif
