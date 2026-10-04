@props([
    'service',
    'stats' => null,
])

@php
    // F76 : note moyenne en texte (« 4,2 / 5 »), nombre d'avis et répartition 5 → 1, sur les avis publiés seulement.
    $stats ??= $service->statistiquesAvis();
@endphp

<div {{ $attributes->class('flex flex-col gap-4 sm:flex-row sm:items-center') }}>
    @if ($stats['total'] === 0)
        <p class="text-ink-2">{{ __('Aucun avis pour le moment.') }}</p>
    @else
        <div class="shrink-0">
            <p class="tn-display text-3xl font-semibold text-ink">
                {{ number_format($stats['moyenne'], 1, ',', ' ') }} <span class="text-lg text-ink-2">/ 5</span>
            </p>
            <p class="text-sm text-ink-2">{{ trans_choice(':count avis|:count avis', $stats['total'], ['count' => $stats['total']]) }}</p>
        </div>
        <dl class="w-full max-w-xs space-y-1" aria-label="{{ __('Répartition des notes') }}">
            @foreach ($stats['repartition'] as $note => $nombre)
                <div class="flex items-center gap-2 text-xs">
                    <dt class="w-14 shrink-0 font-mono text-ink-2">{{ $note }} / 5</dt>
                    <dd class="flex flex-1 items-center gap-2">
                        <span class="h-2 flex-1 overflow-hidden rounded-full bg-line" aria-hidden="true">
                            <span class="block h-full bg-amber" style="width: {{ (int) round($nombre * 100 / $stats['total']) }}%"></span>
                        </span>
                        <span class="w-6 text-end font-mono text-ink">{{ $nombre }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>
