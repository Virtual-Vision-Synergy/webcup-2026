{{--
    F97 : bandeau « Lignes interrompues » (accueil, tableau de bord, page Transports), visible par tous, invités compris.
    Liste mise en cache 60 s (InterruptionTransport::pourBandeau) ; disparaît d'elle-même à la fin de la période.
    L'état est dit en texte et par une icône, pas seulement par la couleur.
--}}
@props(['lien' => true])

@php
    $interruptionsTransport = \App\Models\InterruptionTransport::pourBandeau();
@endphp

@if ($interruptionsTransport !== [])
    <div role="alert" data-test="bandeau-transports" {{ $attributes->class('rounded-md border border-magenta/40 bg-magenta/8 p-4 text-ink') }}>
        <p class="flex items-center gap-2 font-semibold text-magenta">
            <flux:icon name="x-circle" class="size-5 shrink-0" aria-hidden="true" />
            {{ __('Transports : ligne(s) interrompue(s)') }}
        </p>
        <ul class="mt-2 space-y-1.5 text-sm">
            @foreach ($interruptionsTransport as $interruption)
                <li class="flex flex-col gap-0.5 sm:flex-row sm:gap-2">
                    <span class="shrink-0 font-mono font-semibold">
                        {{ collect($interruption['lignes'])->map(fn (string $numero): string => 'Ligne '.$numero)->implode(', ') }} — {{ __('Interrompue') }}
                    </span>
                    <span class="text-ink-2">{{ \Illuminate\Support\Str::limit($interruption['cause'], 160) }}</span>
                </li>
            @endforeach
        </ul>
        @if ($lien)
            <a href="{{ route('transports.index') }}" @auth wire:navigate @endauth class="mt-3 inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-magenta underline underline-offset-2">
                {{ __('Voir comment faire (solutions de remplacement)') }}
                <flux:icon name="arrow-right" class="size-4" aria-hidden="true" />
            </a>
        @endif
    </div>
@endif
