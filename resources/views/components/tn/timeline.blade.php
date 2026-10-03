@props(['items' => []])

{{-- Chronologie : [['date' => Carbon|null, 'label' => '…', 'texte' => '…'|null, 'etat' => 'normal'|…, 'fait' => bool]] --}}
<ol {{ $attributes->class('relative') }}>
    @foreach ($items as $etape)
        @php
            $couleur = match ($etape['etat'] ?? 'info') {
                'normal' => 'bg-green',
                'perturbe' => 'bg-amber',
                'alerte' => 'bg-magenta',
                default => 'bg-cyan',
            };
            $fait = $etape['fait'] ?? true;
        @endphp
        <li class="relative grid grid-cols-[20px_minmax(0,1fr)] gap-3 pb-6 last:pb-0">
            @unless ($loop->last)
                <span class="absolute top-4 bottom-0 left-[9px] w-px bg-line" aria-hidden="true"></span>
            @endunless
            <span class="relative mt-1 flex size-5 items-center justify-center" aria-hidden="true">
                <span @class(['size-2.5 rounded-full', $couleur => $fait, 'border border-ink-2/50' => ! $fait])></span>
            </span>
            <div class="min-w-0">
                <p @class(['font-medium', 'text-ink' => $fait, 'text-ink-2' => ! $fait])>{{ __($etape['label']) }}</p>
                @if (! empty($etape['date']))
                    <time datetime="{{ $etape['date']->toIso8601String() }}" class="font-mono text-xs text-ink-2">{{ $etape['date']->translatedFormat('d M Y · H:i') }}</time>
                @endif
                @if (! empty($etape['texte']))
                    <p class="mt-1 text-sm text-ink-2">{{ __($etape['texte']) }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
