@props(['priorite'])

{{-- F80 : priorité d'une demande, couleur + icône + texte (jamais la couleur seule). --}}
<x-tn.status-badge
    :etat="\App\Models\Demarche::PRIORITE_ETATS[$priorite] ?? 'info'"
    :icon="\App\Models\Demarche::PRIORITE_ICONES[$priorite] ?? null"
    data-test="badge-priorite"
    {{ $attributes }}
>{{ __('Priorité') }} {{ \App\Models\Demarche::libellePriorite($priorite) }}</x-tn.status-badge>
