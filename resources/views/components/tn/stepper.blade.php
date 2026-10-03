@props([
    'steps' => [],
    'current' => 1,
])

{{-- Barre de progression d'un formulaire en étapes ($current = numéro de l'étape, à partir de 1). --}}
<div {{ $attributes }}>
    <div class="flex items-center justify-between font-mono text-[11px] uppercase tracking-[.06em] text-ink-2">
        <span>Étape <span class="text-ink" x-text="{{ $current }}">1</span> / {{ count($steps) }}</span>
        <span class="text-ink" x-text="{{ json_encode(array_values($steps)) }}[{{ $current }} - 1]">{{ $steps[0] ?? '' }}</span>
    </div>
    <div class="mt-2 grid gap-1.5" style="grid-template-columns: repeat({{ max(1, count($steps)) }}, minmax(0, 1fr))" role="progressbar" aria-valuemin="1" aria-valuemax="{{ count($steps) }}" x-bind:aria-valuenow="{{ $current }}">
        @foreach ($steps as $i => $libelle)
            <span class="h-1 rounded-full bg-line transition-colors duration-300" x-bind:class="{{ $current }} > {{ $loop->index }} && 'bg-cyan!'"></span>
        @endforeach
    </div>
</div>
