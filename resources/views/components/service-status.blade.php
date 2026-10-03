@props([
    'service',
    'compact' => false,
])

@php
    // F64 : même affichage de l'état partout (catalogue, fiche) : icône + texte + couleur (F43, jamais la couleur seule).
    $etatBadge = match ($service->etat()) {
        \App\Models\Service::ETAT_INDISPONIBLE => 'alerte',
        \App\Models\Service::ETAT_PERTURBE => 'perturbe',
        default => 'normal',
    };
    $misAJour = $service->etatMisAJourLe()?->setTimezone(\App\Models\CreneauRendezVous::fuseau())->locale('fr');
@endphp

@if ($compact)
    <x-tn.status-badge :etat="$etatBadge" {{ $attributes }} data-service-etat="{{ $service->etat() }}">
        <span class="sr-only">{{ __('État :') }}</span> {{ __($service->libelleEtat()) }}
    </x-tn.status-badge>
@else
    <div {{ $attributes->class('flex flex-wrap items-center gap-x-3 gap-y-1') }} data-service-etat="{{ $service->etat() }}">
        <span class="text-sm font-medium text-ink">{{ __('État actuel') }}</span>
        <x-tn.status-badge :etat="$etatBadge" class="text-xs">{{ __($service->libelleEtat()) }}</x-tn.status-badge>
        @if ($misAJour)
            <span class="text-xs text-ink-2">{{ __('Mis à jour le :date', ['date' => $misAJour->translatedFormat('j F Y à H\hi')]) }}</span>
        @endif
    </div>
@endif
