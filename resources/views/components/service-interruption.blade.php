@props([
    'service',
    'alternatives' => collect(),
])

@php
    // F64 : encart d'interruption (perturbé ou indisponible), affiché AVANT les boutons de démarche et de rendez-vous.
    $indisponible = $service->estIndisponible();
@endphp

@if ($service->etat() !== \App\Models\Service::ETAT_DISPONIBLE)
    <div @class([
        'rounded-md border p-5 md:p-6',
        'border-magenta/35 bg-magenta/8' => $indisponible,
        'border-amber/35 bg-amber/8' => ! $indisponible,
    ]) role="status" {{ $attributes }}>
        <div class="flex items-start gap-3">
            <flux:icon :name="$indisponible ? 'x-circle' : 'exclamation-triangle'" @class(['mt-0.5 size-6 shrink-0', 'text-magenta' => $indisponible, 'text-amber' => ! $indisponible]) aria-hidden="true" />
            <div class="min-w-0 space-y-3">
                <div>
                    <h2 class="font-semibold text-ink">
                        {{ $indisponible ? __('Interruption en cours') : __('Service perturbé : prévoyez des délais') }}
                    </h2>
                    <p class="mt-1 text-ink"><span class="font-medium">{{ __('Motif :') }}</span> {{ $service->motif_indisponibilite ?: __('Information en attente de la mairie.') }}</p>
                    <p class="text-sm text-ink-2">
                        {{ $indisponible
                            ? __('Les démarches et les rendez-vous en ligne sont suspendus pour ce service.')
                            : __('Vous pouvez faire votre démarche, mais son traitement peut prendre plus de temps que d\'habitude.') }}
                    </p>
                </div>

                <ul class="space-y-2 text-sm text-ink">
                    <li class="flex gap-2">
                        <flux:icon name="calendar-days" class="mt-0.5 size-4 shrink-0 text-cyan" aria-hidden="true" />
                        <span>
                            {{ __($service->libelleRetourPrevu()) }}.
                            @if ($service->retourPrevuDepasse())
                                <strong class="block text-magenta">{{ __('Retour prévu dépassé, informations en cours de mise à jour.') }}</strong>
                            @endif
                        </span>
                    </li>
                    @if (filled($service->alternative_texte) || filled($service->alternative_url))
                        <li class="flex gap-2">
                            <flux:icon name="arrow-right-circle" class="mt-0.5 size-4 shrink-0 text-cyan" aria-hidden="true" />
                            <span>
                                <span class="font-medium">{{ __('Alternative proposée :') }}</span>
                                {{ $service->alternative_texte }}
                                @if (filled($service->alternative_url))
                                    <a href="{{ $service->alternative_url }}" target="_blank" rel="noopener noreferrer" class="text-cyan hover:underline">{{ __('Ouvrir l\'alternative') }}<span class="sr-only"> {{ __('(nouvel onglet)') }}</span></a>
                                @endif
                            </span>
                        </li>
                    @endif
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
