<?php

use App\Models\ActionLog;
use App\Services\RapportActivite;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * F103 : version imprimable du rapport d'activité (Imprimer → Enregistrer en PDF) + export CSV des chiffres.
 * Réservé aux admins (Gate voirRapportActivite) ; la période vient de l'URL et est revalidée par le service.
 */
new #[Layout('layouts::imprimable'), Title('Rapport d’activité')] class extends Component {
    #[Url]
    public string $periode = '30';

    #[Url]
    public string $du = '';

    #[Url]
    public string $au = '';

    public function mount(): void
    {
        Gate::authorize('voirRapportActivite');
    }

    #[Computed]
    public function rapport(): RapportActivite
    {
        Gate::authorize('voirRapportActivite');

        return new RapportActivite($this->periode, $this->du, $this->au);
    }

    public function export(): StreamedResponse
    {
        Gate::authorize('voirRapportActivite');

        $csv = $this->rapport->csv();

        ActionLog::record('export_rapport_activite');

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, 'rapport-activite-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}; ?>

@php
    $rapport = $this->rapport;
    $chiffres = $rapport->chiffres();
    $services = $rapport->services();
    $quartiers = $rapport->quartiers();
    $marques = ['alerte' => 'Urgent', 'attention' => 'À surveiller', 'info' => 'À noter', 'succes' => 'Bonne nouvelle'];
@endphp

<div class="space-y-8 text-zinc-900">
    {{-- Barre d'actions (masquée à l'impression) --}}
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ \App\Filament\Pages\RapportActivite::getUrl() }}" class="text-sm font-medium text-cyan underline underline-offset-2">← Retour au rapport dans l’administration</a>
        <div class="flex flex-wrap gap-2">
            <button type="button" onclick="window.print()" class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-on-cyan hover:opacity-90">
                Imprimer / Enregistrer en PDF
            </button>
            <button type="button" wire:click="export" wire:loading.attr="disabled" class="rounded-md border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-50">
                <span wire:loading.remove wire:target="export">Télécharger les chiffres (CSV)</span>
                <span wire:loading wire:target="export">Préparation…</span>
            </button>
        </div>
    </div>

    {{-- En-tête du document --}}
    <header class="border-b-2 border-zinc-900 pb-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-zinc-500">Mairie de Nova Terra · Terra Nova</p>
        <h1 class="mt-1 text-2xl font-bold sm:text-3xl">Rapport d’activité de la plateforme</h1>
        <p class="mt-2 text-sm text-zinc-600">
            Période du {{ $rapport->debut->format('d/m/Y') }} au {{ $rapport->fin->format('d/m/Y') }},
            comparée au {{ $rapport->debutPrecedent->format('d/m/Y') }} – {{ $rapport->finPrecedente->format('d/m/Y') }}
            · édité le {{ now()->format('d/m/Y à H:i') }} par {{ auth()->user()->name }}
        </p>
        @if ($rapport->periodeIgnoree)
            <p role="alert" class="mt-2 text-sm font-semibold text-amber-700 print:hidden">Les dates demandées sont invalides : le rapport porte sur les 30 derniers jours.</p>
        @endif
    </header>

    {{-- Synthèse --}}
    <section aria-labelledby="synthese" class="space-y-3">
        <h2 id="synthese" class="text-lg font-semibold">Synthèse</h2>
        <div class="space-y-2 rounded-md bg-zinc-100 p-4 text-sm leading-relaxed print:border print:border-zinc-300 print:bg-white">
            @foreach ($rapport->synthese() as $phrase)
                <p>{{ $phrase }}</p>
            @endforeach
        </div>
    </section>

    {{-- Points d'attention --}}
    <section aria-labelledby="points" class="space-y-3 break-inside-avoid">
        <h2 id="points" class="text-lg font-semibold">Points d’attention</h2>
        <ol class="space-y-2">
            @foreach ($rapport->pointsAttention() as $point)
                <li class="rounded-md border border-zinc-300 p-3 text-sm leading-relaxed">
                    <span class="font-semibold uppercase tracking-wide text-xs text-zinc-600">{{ $marques[$point['niveau']] ?? 'À noter' }}</span>
                    <span class="block">{{ $point['texte'] }}</span>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Chiffres clés --}}
    <section aria-labelledby="chiffres" class="space-y-3 break-inside-avoid">
        <h2 id="chiffres" class="text-lg font-semibold">Chiffres clés</h2>
        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 print:grid-cols-3">
            @foreach ([
                ['Demandes déposées', $chiffres['demandes'], RapportActivite::tendance($chiffres['evolution_demandes'])],
                ['Taux de traitement', $chiffres['taux_traitement'] === null ? '—' : $chiffres['taux_traitement'].' %', $chiffres['taux_traitement_avant'] === null ? 'sans comparaison possible' : $chiffres['taux_traitement_avant'].' % auparavant'],
                ['Délai moyen de traitement', RapportActivite::formatDelai($chiffres['delai']), RapportActivite::tendance($chiffres['evolution_delai'])],
                ['Signalements', $chiffres['signalements'], RapportActivite::tendance($chiffres['evolution_signalements'])],
                ['Urgences médicales', $chiffres['urgences'], $chiffres['urgences_ouvertes'].' encore ouverte(s)'],
                ['En attente depuis plus de '.RapportActivite::JOURS_ATTENTE.' jours', $chiffres['en_attente'], 'demande(s) sans prise en charge'],
            ] as [$libelle, $valeur, $detail])
                <div class="rounded-md border border-zinc-300 p-3">
                    <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ $libelle }}</dt>
                    <dd class="text-2xl font-bold">{{ $valeur }}</dd>
                    <dd class="text-xs font-medium text-zinc-600">{{ $detail }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Services --}}
    <section aria-labelledby="services" class="space-y-3">
        <h2 id="services" class="text-lg font-semibold">Demandes par service</h2>

        @if ($services->isEmpty())
            <p class="rounded-md border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-600">Aucune demande sur cette période.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b-2 border-zinc-900">
                            <th scope="col" class="py-2 pe-3">Service</th>
                            <th scope="col" class="py-2 pe-3 text-right">Demandes</th>
                            <th scope="col" class="py-2 pe-3 text-right">Évolution</th>
                            <th scope="col" class="py-2 pe-3 text-right">Traitées</th>
                            <th scope="col" class="py-2 pe-3 text-right">Délai moyen</th>
                            <th scope="col" class="py-2 text-right">Délai précédent</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($services as $ligne)
                            <tr wire:key="s-{{ $loop->index }}" class="border-b border-zinc-200 break-inside-avoid">
                                <td class="py-2 pe-3 font-medium">{{ $ligne['nom'] }}</td>
                                <td class="py-2 pe-3 text-right">{{ $ligne['demandes'] }}</td>
                                <td class="py-2 pe-3 text-right whitespace-nowrap">{{ $ligne['evolution'] === null ? '—' : RapportActivite::tendance($ligne['evolution']) }}</td>
                                <td class="py-2 pe-3 text-right">{{ $ligne['traitees'] }}</td>
                                <td class="py-2 pe-3 text-right whitespace-nowrap">{{ RapportActivite::formatDelai($ligne['delai']) }}</td>
                                <td class="py-2 text-right whitespace-nowrap">{{ RapportActivite::formatDelai($ligne['delai_avant']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Signalements par quartier --}}
    <section aria-labelledby="quartiers" class="space-y-3 break-inside-avoid">
        <h2 id="quartiers" class="text-lg font-semibold">Signalements par quartier</h2>

        @if ($quartiers->isEmpty())
            <p class="rounded-md border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-600">Aucun signalement sur cette période.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b-2 border-zinc-900">
                            <th scope="col" class="py-2 pe-3">Quartier</th>
                            <th scope="col" class="py-2 pe-3 text-right">Signalements</th>
                            <th scope="col" class="py-2 pe-3 text-right">Encore ouverts</th>
                            <th scope="col" class="py-2 text-right">Période précédente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quartiers as $quartier)
                            <tr wire:key="q-{{ $loop->index }}" class="border-b border-zinc-200">
                                <td class="py-2 pe-3 font-medium">{{ $quartier['nom'] }}</td>
                                <td class="py-2 pe-3 text-right">{{ $quartier['signalements'] }}</td>
                                <td class="py-2 pe-3 text-right">{{ $quartier['ouverts'] }}</td>
                                <td class="py-2 text-right">{{ $quartier['avant'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <footer class="border-t border-zinc-300 pt-3 text-xs text-zinc-500">
        Document interne généré depuis Terra Nova, réservé aux responsables. Chiffres agrégés, sans donnée personnelle.
        Délai moyen : du dépôt à la réponse, sur les demandes traitées ou refusées pendant la période.
    </footer>
</div>
