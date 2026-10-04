{{-- Carte Leaflet : voir app/View/Components/Carte.php et resources/js/carte.js --}}
@if ($differee())
    {{--
        F96 : version légère. Adresse et itinéraire tout de suite ; la carte (et son script) seulement au clic
        sur « Afficher la carte » (resources/js/app.js charge alors resources/js/carte.js).
    --}}
    <div
        {{ $attributes->class('space-y-2') }}
        wire:ignore
        wire:key="carte-{{ md5(json_encode($config())) }}"
        data-carte-differee="{{ json_encode($config()) }}"
    >
        @if ($itineraire && count($points) > 0 && count($points) <= 3)
            <ul class="space-y-2 text-sm">
                @foreach ($points as $point)
                    <li>
                        @if ($point['titre'] !== '')
                            <p class="font-medium text-ink">{{ $point['titre'] }}</p>
                        @endif
                        @foreach ($point['lignes'] as $ligne)
                            <p class="text-ink-2">{{ $ligne }}</p>
                        @endforeach
                        <a href="{{ $lienItineraire($point) }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-1 text-cyan hover:underline">
                            <flux:icon name="arrow-top-right-on-square" class="size-4" />{{ __('Itinéraire (nouvel onglet)') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <flux:button type="button" icon="map" class="min-h-11" data-carte-afficher data-test="carte-afficher">
            {{ __('Afficher la carte') }}
        </flux:button>

        <div
            data-carte-zone
            hidden
            role="region"
            aria-label="{{ $label }}"
            class="z-0 w-full overflow-hidden rounded-md border border-line bg-surface-2"
            style="height: {{ $hauteur }}"
        ></div>

        <p data-carte-message aria-live="polite" class="text-sm text-ink-2"></p>
    </div>
@else
    <div
        {{ $attributes->class('space-y-2') }}
        wire:ignore
        wire:key="carte-{{ md5(json_encode($config())) }}"
        data-carte="{{ json_encode($config()) }}"
    >
        @vite('resources/js/carte.js')

        @if ($mode === 'choix')
            <div class="flex flex-wrap items-center gap-3">
                <flux:button type="button" size="sm" icon="map-pin" data-carte-localiser>{{ __('Me localiser') }}</flux:button>
                <flux:text class="text-sm">{{ __('Ou cliquez sur la carte pour placer le repère.') }}</flux:text>
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
@endif
