{{-- Carte Leaflet : voir app/View/Components/Carte.php et resources/js/carte.js --}}
<div
    {{ $attributes->class('space-y-2') }}
    wire:ignore
    wire:key="carte-{{ md5(json_encode($config())) }}"
    data-carte="{{ json_encode($config()) }}"
>
    @vite('resources/js/carte.js')

    @if ($mode === 'choix')
        <div class="flex flex-wrap items-center gap-3">
            <flux:button type="button" size="sm" icon="map-pin" data-carte-localiser>Me localiser</flux:button>
            <flux:text class="text-sm">Ou cliquez sur la carte pour placer le repère.</flux:text>
        </div>
    @endif

    <div
        data-carte-zone
        role="region"
        aria-label="{{ $label }}"
        class="z-0 w-full overflow-hidden rounded-md border border-line bg-surface-2"
        style="height: {{ $hauteur }}"
    ></div>

    <p data-carte-message aria-live="polite" class="text-sm text-ink-2"></p>
</div>
