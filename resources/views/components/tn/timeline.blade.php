@props(['items' => []])

{{-- Chronologie : [['date' => Carbon|null, 'label' => '…', 'texte' => '…'|null, 'etat' => 'normal'|…, 'fait' => bool, 'courant' => bool (facultatif : étape actuelle mise en évidence)]] --}}
<ol {{ $attributes->class('relative') }}>
    @foreach ($items as $etape)
        @php
            // F43 : l'état d'une étape est porté par la couleur ET par la forme de l'icône (+ texte pour lecteur d'écran).
            [$couleur, $icone, $libelleEtat] = match ($etape['etat'] ?? 'info') {
                'normal' => ['text-green', 'check-circle', 'normal'],
                'perturbe' => ['text-amber', 'exclamation-triangle', 'perturbé'],
                'alerte' => ['text-magenta', 'x-circle', 'alerte'],
                default => ['text-cyan', 'information-circle', 'information'],
            };
            $fait = $etape['fait'] ?? true;
            $courant = $etape['courant'] ?? false;
        @endphp
        <li class="relative grid grid-cols-[20px_minmax(0,1fr)] gap-3 pb-6 last:pb-0" @if ($courant) aria-current="step" @endif>
            @unless ($loop->last)
                <span class="absolute top-6 bottom-0 left-[9px] w-px bg-line" aria-hidden="true"></span>
            @endunless
            <span class="relative mt-0.5 flex size-5 items-center justify-center" aria-hidden="true">
                @if ($fait)
                    <flux:icon :name="$icone" variant="mini" @class(['size-5', $couleur]) />
                @else
                    <span class="size-2.5 rounded-full border border-dashed border-ink-2"></span>
                @endif
            </span>
            <div class="min-w-0">
                <p @class(['font-medium', 'text-ink' => $fait && ! $courant, 'text-ink-2' => ! $fait, 'text-cyan' => $courant])>
                    {{ $etape['label'] }}
                    @if ($courant)
                        <span class="ms-1 rounded-xs border border-cyan/35 bg-cyan/8 px-1.5 py-0.5 font-mono text-[0.625rem] uppercase tracking-[.06em] text-cyan">Étape actuelle</span>
                    @endif
                    <span class="sr-only">({{ $fait ? 'étape franchie, état : '.$libelleEtat : 'étape à venir' }})</span>
                </p>
                @if (! empty($etape['date']))
                    <time datetime="{{ $etape['date']->toIso8601String() }}" class="font-mono text-xs text-ink-2">{{ $etape['date']->translatedFormat('d M Y · H:i') }}</time>
                @endif
                @if (! empty($etape['texte']))
                    <p class="mt-1 text-sm text-ink-2">{{ $etape['texte'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
