@props([
    'label' => null,
    'padding' => 'p-5 md:p-6',
])

{{-- Panneau chanfreiné : réservé aux blocs clés (état, carte, mise en avant). --}}
<div {{ $attributes->class('tn-panel') }}>
    <div @class(['tn-panel-inner', $padding])>
        @if ($label)
            <x-tn.section-label class="mb-4">{{ is_string($label) ? __($label) : $label }}</x-tn.section-label>
        @endif
        {{ $slot }}
    </div>
</div>
