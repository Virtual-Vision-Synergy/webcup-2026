@props([
    'service',
    'compact' => false,
])

@php
    // F64 : même affichage de l'état partout (catalogue, fiche) : icône + texte + couleur (F43, jamais la couleur seule).
    // F99 : accepte aussi un service partenaire (Disponible / Complet / Indisponible jusqu'au …).
    $estPartenaire = $service instanceof \App\Models\PartnerOffering;
    $etatBadge = $estPartenaire ? $service->etatBadge() : match ($service->etat()) {
        \App\Models\Service::ETAT_INDISPONIBLE => 'alerte',
        \App\Models\Service::ETAT_PERTURBE => 'perturbe',
        default => 'normal',
    };
    $icone = $estPartenaire ? $service->iconeStatut() : null;
    $codeEtat = $estPartenaire ? $service->status : $service->etat();
    $misAJour = ($estPartenaire ? $service->updated_at : $service->etatMisAJourLe())?->setTimezone(\App\Models\CreneauRendezVous::fuseau());
@endphp

@if ($compact)
    <x-tn.status-badge :etat="$etatBadge" :icon="$icone" {{ $attributes }} data-service-etat="{{ $codeEtat }}">
        <span class="sr-only">{{ __('État :') }}</span> {{ __($service->libelleEtatDetaille()) }}
    </x-tn.status-badge>
@else
    <div {{ $attributes->class('flex flex-wrap items-center gap-x-3 gap-y-1') }} data-service-etat="{{ $codeEtat }}">
        <span class="text-sm font-medium text-ink">{{ __('État actuel') }}</span>
        <x-tn.status-badge :etat="$etatBadge" :icon="$icone" class="text-xs">{{ __($service->libelleEtatDetaille()) }}</x-tn.status-badge>
        @if ($misAJour)
            <span class="text-xs text-ink-2">{{ __('Mis à jour le :date', ['date' => $misAJour->isoFormat('LLL')]) }}</span>
        @endif
    </div>
@endif
