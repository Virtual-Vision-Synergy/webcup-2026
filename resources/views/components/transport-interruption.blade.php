{{--
    F97 : « voici comment faire » pour une ligne interrompue, en langage clair : cause, fin prévue, effet sur l'arrêt
    habituel de l'habitant et solutions de remplacement saisies par l'agent (carte si un point de départ est donné).
--}}
@props([
    'interruption',
    'ligne',
    'arret' => null,
    'personnel' => false,
    'carte' => false,
])

@php
    /** @var \App\Models\InterruptionTransport $interruption */
    /** @var \App\Models\LigneTransport $ligne */
    $solutions = $interruption->solutionsAffichables();
    $arretsTouches = $interruption->listeArretsTouches();
    $arretTouche = $interruption->toucheArret($arret);
    $points = collect($solutions)
        ->filter(fn (array $s): bool => $s['lat'] !== null)
        ->map(fn (array $s): array => ['lat' => $s['lat'], 'lng' => $s['lng'], 'titre' => $s['titre'], 'etat' => 'info', 'lignes' => array_filter([$s['label'], $s['horaires']])])
        ->values()
        ->all();
@endphp

<div role="alert" data-test="interruption-ligne-{{ $ligne->id }}" {{ $attributes->class('rounded-md border border-magenta/40 bg-magenta/8 p-4 md:p-5') }}>
    <p class="flex items-start gap-2 font-semibold text-magenta">
        <flux:icon name="x-circle" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
        <span>
            @if ($personnel)
                {{ __('Votre ligne :numero est interrompue, voici comment faire', ['numero' => $ligne->numero]) }}
            @else
                {{ __('Ligne :numero interrompue : voici comment faire', ['numero' => $ligne->numero]) }}
            @endif
        </span>
    </p>

    <p class="mt-2 text-sm text-ink">{{ $interruption->cause }}</p>
    <p class="mt-1 text-sm text-ink-2">{{ __('Interruption prévue :fin.', ['fin' => $interruption->libelleFin()]) }}</p>

    @if ($personnel && $arret)
        <p class="mt-2 flex items-start gap-1.5 text-sm font-medium text-ink">
            <flux:icon name="map-pin" class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            @if ($arretTouche)
                {{ __('Votre arrêt « :arret » n’est plus desservi pendant l’interruption.', ['arret' => $arret]) }}
            @else
                {{ __('Votre arrêt « :arret » reste desservi, mais la ligne ne passe plus par : :liste.', ['arret' => $arret, 'liste' => implode(', ', $arretsTouches)]) }}
            @endif
        </p>
    @elseif ($arretsTouches !== [])
        <p class="mt-2 text-sm text-ink">{{ __('Arrêts non desservis : :liste.', ['liste' => implode(', ', $arretsTouches)]) }}</p>
    @else
        <p class="mt-2 text-sm text-ink">{{ __('Toute la ligne est arrêtée.') }}</p>
    @endif

    @if ($solutions !== [])
        <p class="mt-4 text-sm font-semibold text-ink">{{ __('Solutions de remplacement') }}</p>
        <ol class="mt-2 grid gap-2 sm:grid-cols-2">
            @foreach ($solutions as $i => $solution)
                <li class="rounded-md border border-line bg-surface p-3 text-sm">
                    <p class="flex items-start gap-2 font-semibold text-ink">
                        <flux:icon :name="$solution['icone']" class="mt-0.5 size-4 shrink-0 text-cyan" aria-hidden="true" />
                        <span><span class="sr-only">{{ __('Solution :n :', ['n' => $i + 1]) }}</span>{{ $solution['titre'] }}</span>
                    </p>
                    <p class="mt-0.5 font-mono text-[0.6875rem] uppercase tracking-[.06em] text-ink-2">{{ __($solution['label']) }}</p>
                    @if ($solution['description'] !== '')
                        <p class="mt-1.5 whitespace-pre-line text-ink-2">{{ $solution['description'] }}</p>
                    @endif
                    @if ($solution['horaires'] !== '')
                        <p class="mt-1.5 flex items-start gap-1.5 text-ink-2">
                            <flux:icon name="clock" class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            <span>{{ $solution['horaires'] }}</span>
                        </p>
                    @endif
                    @if ($solution['ligne'])
                        <a href="{{ route('transports.show', $solution['ligne']) }}" wire:navigate class="mt-1.5 inline-flex min-h-11 items-center gap-1 font-medium text-cyan underline underline-offset-2">
                            {{ __('Voir la ligne :numero (:nom)', ['numero' => $solution['ligne']->numero, 'nom' => $solution['ligne']->nom]) }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ol>
    @else
        <p class="mt-3 text-sm text-ink-2">{{ __('Les solutions de remplacement seront publiées très bientôt.') }}</p>
    @endif

    @if ($carte && $points !== [])
        <div class="mt-4">
            <x-carte :points="$points" :centre="[$points[0]['lat'], $points[0]['lng']]" :zoom="14" hauteur="16rem" :label="__('Points de départ des solutions de remplacement')" />
        </div>
    @endif
</div>
