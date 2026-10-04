@props([
    'service',
    'alternatives' => collect(),
])

@php
    // F64 : encart d'interruption (perturbé ou indisponible), affiché AVANT les boutons de démarche et de rendez-vous.
    // Une interruption F38 en cours (espace agent) fournit motif, retour et alternative ; sinon les champs F63 / F64.
    $indisponible = $service->estIndisponible();
    $interruption = $service->interruptionEnCours();
    $lienAlternative = $service->alternativeLienEtat();
@endphp

@if ($service->etat() !== \App\Models\Service::ETAT_DISPONIBLE)
    <div @class([
        'space-y-4 rounded-md border p-5 md:p-6',
        'border-magenta/35 bg-magenta/8' => $indisponible,
        'border-amber/35 bg-amber/8' => ! $indisponible,
    ]) role="status" data-test="interruption" {{ $attributes }}>
        <div class="flex items-start gap-3">
            <flux:icon :name="$indisponible ? 'x-circle' : 'exclamation-triangle'" @class(['mt-0.5 size-6 shrink-0', 'text-magenta' => $indisponible, 'text-amber' => ! $indisponible]) aria-hidden="true" />
            <div class="min-w-0 space-y-3">
                <div>
                    <h2 class="tn-display text-lg font-semibold text-ink">
                        {{ $indisponible ? __('Ce service est momentanément indisponible') : __('Service perturbé : prévoyez des délais') }}
                    </h2>
                    @if ($interruption)
                        <p class="text-sm text-ink-2">{{ __($interruption->libelleType()) }} · {{ __('depuis le :date', ['date' => \App\Models\ServiceInterruption::libelleDate($interruption->debut_at)]) }}</p>
                    @endif
                    <p class="mt-1 text-ink"><span class="font-medium">{{ __('Motif :') }}</span> {{ $service->motifEtat() ?: __('Information en attente de la mairie.') }}</p>
                    <p class="mt-2 font-medium text-ink">
                        <flux:icon name="calendar-days" class="me-1 inline size-4 align-[-2px] text-cyan" aria-hidden="true" />{{ __($service->libelleRetourPrevu()) }}
                    </p>
                    @if (! $interruption && $service->retourPrevuDepasse())
                        <p class="font-semibold text-magenta">{{ __('Retour prévu dépassé, informations en cours de mise à jour.') }}</p>
                    @endif
                    <p class="mt-1 text-sm text-ink-2">
                        {{ $indisponible
                            ? __('Les nouvelles démarches et les rendez-vous en ligne sont suspendus. Les démarches déjà déposées et les rendez-vous déjà pris restent enregistrés.')
                            : __('Vous pouvez faire votre démarche, mais son traitement peut prendre plus de temps que d\'habitude.') }}
                    </p>
                </div>

                @if (filled($service->alternativeTexteEtat()) || $lienAlternative !== null)
                    <div class="rounded-sm border border-line bg-surface p-4">
                        <h3 class="font-semibold text-ink">{{ __('Que faire en attendant ?') }}</h3>
                        @if (filled($service->alternativeTexteEtat()))
                            <p class="mt-1 whitespace-pre-line text-ink">{{ $service->alternativeTexteEtat() }}</p>
                        @endif
                        @if ($lienAlternative !== null && $lienAlternative['interne'])
                            <flux:link :href="$lienAlternative['url']" wire:navigate class="mt-2 inline-flex items-center gap-1">{{ __('Voir le service :nom', ['nom' => __($lienAlternative['libelle'])]) }}</flux:link>
                        @elseif ($lienAlternative !== null)
                            <a href="{{ $lienAlternative['url'] }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex items-center gap-1 text-cyan hover:underline">{{ __($lienAlternative['libelle']) }}<span class="sr-only"> {{ __('(nouvel onglet)') }}</span></a>
                        @endif
                    </div>
                @endif

                <ul class="space-y-2 text-sm text-ink">
                    @if ($alternatives->isNotEmpty())
                        <li class="flex gap-2">
                            <flux:icon name="building-office-2" class="mt-0.5 size-4 shrink-0 text-cyan" aria-hidden="true" />
                            <span>
                                {{ __('Autre service disponible :') }}
                                @foreach ($alternatives as $alternative)
                                    <a href="{{ route('services.show', $alternative) }}" wire:navigate class="text-cyan hover:underline">{{ __($alternative->nom) }}</a>@if (! $loop->last), @endif
                                @endforeach
                            </span>
                        </li>
                    @endif
                    <li class="flex gap-2">
                        <flux:icon name="phone" class="mt-0.5 size-4 shrink-0 text-cyan" aria-hidden="true" />
                        <span>
                            {{ __('Contactez la mairie') }}@if ($service->telephone) {{ __('au') }} <a href="tel:{{ preg_replace('/[^0-9+]/', '', $service->telephone) }}" class="font-mono text-cyan hover:underline">{{ $service->telephone }}</a>@endif
                            @if (Route::has('messages.create'))
                                {{ __('ou') }} <a href="{{ route('messages.create') }}" wire:navigate class="text-cyan hover:underline">{{ __('envoyez un message') }}</a>
                            @endif
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
@endif
