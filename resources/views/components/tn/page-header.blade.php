@props([
    'label' => null,
    'title',
    'subtitle' => null,
    'breadcrumb' => [],
])

{{-- En-tête de page : fil d'Ariane, intitulé de rubrique, titre, sous-titre, actions (slot). --}}
<header {{ $attributes->class('flex flex-col gap-4 border-b border-line pb-6 md:flex-row md:items-end md:justify-between') }}>
    <div class="min-w-0">
        @if (count($breadcrumb))
            <x-tn.breadcrumb :items="$breadcrumb" class="mb-3" />
        @endif
        @if ($label)
            <x-tn.section-label class="mb-2 text-cyan!">{{ is_string($label) ? __($label) : $label }}</x-tn.section-label>
        @endif
        <h1 class="tn-display text-[1.75rem] leading-[1.1] font-semibold text-ink md:text-[2.25rem]">{{ is_string($title) ? __($title) : $title }}</h1>
        @if ($subtitle)
            <p class="mt-2 text-ink-2">{{ is_string($subtitle) ? __($subtitle) : $subtitle }}</p>
        @endif
        {{ $meta ?? '' }}
    </div>
    @if (isset($actions) && $actions->isNotEmpty())
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endif
</header>
