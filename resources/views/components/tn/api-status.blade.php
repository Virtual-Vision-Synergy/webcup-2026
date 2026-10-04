@php
    use App\Services\NovaTerraApiClient;
    use Illuminate\Support\Facades\Cache;

    // Lecture du cache seulement (aucun appel réseau dans la page) : rempli par l'espace agent.
    $etatApi = match (true) {
        Cache::has(NovaTerraApiClient::FRESH_CACHE_KEY) => ['etat' => 'normal', 'texte' => __('API en ligne')],
        Cache::has(NovaTerraApiClient::LAST_KNOWN_CACHE_KEY) => ['etat' => 'perturbe', 'texte' => __('API en veille')],
        default => ['etat' => 'info', 'texte' => __('Réseau civique')],
    };
@endphp

<x-tn.status-badge :etat="$etatApi['etat']" :live="$etatApi['etat'] === 'normal'" {{ $attributes }}>
    {{ $etatApi['texte'] }}
</x-tn.status-badge>
