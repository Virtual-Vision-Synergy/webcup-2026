@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-2">
    <h1 class="tn-display text-[1.75rem] font-semibold leading-tight text-ink">{{ $title }}</h1>
    <p class="text-ink-2">{{ $description }}</p>
</div>
