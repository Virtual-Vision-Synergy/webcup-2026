<x-filament-panels::page>
    @php
        $rapport = $this->rapport;
        $chiffres = $rapport->chiffres();
        $services = $rapport->services();
        $quartiers = $rapport->quartiers();
        $maxQuartier = max(1, (int) $quartiers->max('signalements'));
        $tendance = fn (?int $e): string => \App\Services\RapportActivite::tendance($e);
        $delai = fn (?float $j): string => \App\Services\RapportActivite::formatDelai($j);
        // Une hausse est mauvaise pour un délai, neutre pour un volume.
        $couleurDelai = fn (?int $e): string => $e === null || $e === 0 ? '' : ($e > 0 ? 'color: rgb(220,38,38);' : 'color: rgb(22,163,74);');
        $couleurs = ['succes' => 'success', 'info' => 'info', 'attention' => 'warning', 'alerte' => 'danger'];
        $icones = ['succes' => 'heroicon-o-check-circle', 'info' => 'heroicon-o-information-circle', 'attention' => 'heroicon-o-exclamation-circle', 'alerte' => 'heroicon-o-exclamation-triangle'];
        $carte = 'padding: 1rem; border-radius: 0.75rem; border: 1px solid rgba(127,127,127,0.25);';
    @endphp

    {{-- Styles en ligne : le CSS précompilé de Filament ne contient pas les utilitaires Tailwind du site. --}}

    {{-- Période et téléchargements --}}
    <x-filament::section>
        <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));">
            <label style="display: grid; gap: 0.25rem; font-size: 0.875rem;">
                <span style="font-weight: 600;">Période</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="periode">
                        @foreach (\App\Services\RapportActivite::PERIODES as $valeur => $libelle)
                            <option value="{{ $valeur }}">{{ $libelle }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>

            @if ($this->periode === 'perso')
                <label style="display: grid; gap: 0.25rem; font-size: 0.875rem;">
                    <span style="font-weight: 600;">Du</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="date" wire:model.live="du" max="{{ now()->toDateString() }}" />
                    </x-filament::input.wrapper>
                </label>

                <label style="display: grid; gap: 0.25rem; font-size: 0.875rem;">
                    <span style="font-weight: 600;">Au</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="date" wire:model.live="au" max="{{ now()->toDateString() }}" />
                    </x-filament::input.wrapper>
                </label>
            @endif
        </div>

        @if ($rapport->periodeIgnoree)
            <p role="alert" style="margin-top: 0.75rem; font-size: 0.85rem; font-weight: 600; color: rgb(217,119,6);">
                Choisissez une date de début et une date de fin valides (fin au plus tard aujourd’hui, {{ \App\Services\RapportActivite::JOURS_MAXIMUM }} jours au maximum). En attendant, le rapport porte sur les 30 derniers jours.
            </p>
        @endif

        <p style="margin-top: 0.75rem; font-size: 0.8rem; opacity: 0.7;">
            Du {{ $rapport->debut->format('d/m/Y') }} au {{ $rapport->fin->format('d/m/Y') }}, comparé au
            {{ $rapport->debutPrecedent->format('d/m/Y') }} – {{ $rapport->finPrecedente->format('d/m/Y') }}.
        </p>

        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem;">
            <x-filament::button tag="a" :href="$this->lienImprimable()" target="_blank" icon="heroicon-o-printer">
                Version imprimable / PDF
            </x-filament::button>
            <x-filament::button color="gray" icon="heroicon-o-arrow-down-tray" wire:click="exporter" wire:loading.attr="disabled" wire:target="exporter">
                Télécharger les chiffres (CSV)
            </x-filament::button>
        </div>

        <p wire:loading wire:target="periode, du, au" style="margin-top: 0.5rem; font-size: 0.8rem; font-weight: 600;">Mise à jour du rapport…</p>
    </x-filament::section>

    {{-- Synthèse en phrases --}}
    <x-filament::section heading="Synthèse" description="Rédigée automatiquement à partir des chiffres (règles, sans IA).">
        <div style="display: grid; gap: 0.6rem; font-size: 0.95rem; line-height: 1.6;">
            @foreach ($rapport->synthese() as $phrase)
                <p>{{ $phrase }}</p>
            @endforeach
        </div>
    </x-filament::section>

    {{-- Points d'attention --}}
    <x-filament::section heading="Points d’attention" description="Ce qui mérite une décision, du plus important au moins important.">
        <div style="display: grid; gap: 0.75rem;">
            @foreach ($rapport->pointsAttention() as $point)
                <x-filament::callout
                    :color="$couleurs[$point['niveau']] ?? 'info'"
                    :icon="$icones[$point['niveau']] ?? 'heroicon-o-information-circle'"
                    :description="$point['texte']"
                />
            @endforeach
        </div>
    </x-filament::section>

    {{-- Chiffres clés --}}
    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));">
        @foreach ([
            ['Demandes déposées', $chiffres['demandes'], $tendance($chiffres['evolution_demandes']), ''],
            ['Taux de traitement', $chiffres['taux_traitement'] === null ? '—' : $chiffres['taux_traitement'].' %', $chiffres['taux_traitement_avant'] === null ? 'sans comparaison possible' : $chiffres['taux_traitement_avant'].' % auparavant', ''],
            ['Délai moyen de traitement', $delai($chiffres['delai']), $tendance($chiffres['evolution_delai']), $couleurDelai($chiffres['evolution_delai'])],
            ['Signalements', $chiffres['signalements'], $tendance($chiffres['evolution_signalements']), ''],
            ['Urgences médicales', $chiffres['urgences'], $chiffres['urgences_ouvertes'].' encore ouverte(s)', $chiffres['urgences_sans_prise_en_charge'] > 0 ? 'color: rgb(220,38,38);' : ''],
        ] as [$libelle, $valeur, $detail, $style])
            <div style="{{ $carte }}">
                <div style="font-size: 0.8rem; opacity: 0.7;">{{ $libelle }}</div>
                <div style="font-size: 1.5rem; font-weight: 700;">{{ $valeur }}</div>
                <div style="font-size: 0.8rem; font-weight: 600; {{ $style }}">{{ $detail }}</div>
            </div>
        @endforeach
    </div>

    {{-- Services --}}
    <x-filament::section heading="Demandes par service" description="Du plus demandé au moins demandé, avec le délai moyen des demandes clôturées sur la période.">
        <div style="overflow-x: auto;">
            <table style="width: 100%; font-size: 0.85rem; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid rgba(127,127,127,0.3);">
                        <th scope="col" style="padding: 0.5rem;">Service</th>
                        <th scope="col" style="padding: 0.5rem; text-align: right;">Demandes</th>
                        <th scope="col" style="padding: 0.5rem; text-align: right;">Évolution</th>
                        <th scope="col" style="padding: 0.5rem; text-align: right;">Traitées</th>
                        <th scope="col" style="padding: 0.5rem; text-align: right;">Délai moyen</th>
                        <th scope="col" style="padding: 0.5rem; text-align: right;">Délai précédent</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($services as $ligne)
                        <tr wire:key="rapport-service-{{ $loop->index }}" style="border-bottom: 1px solid rgba(127,127,127,0.12);">
                            <td style="padding: 0.5rem; font-weight: 600;">{{ $ligne['nom'] }}</td>
                            <td style="padding: 0.5rem; text-align: right;">{{ $ligne['demandes'] }}</td>
                            <td style="padding: 0.5rem; text-align: right; white-space: nowrap;">{{ $ligne['evolution'] === null ? '—' : $tendance($ligne['evolution']) }}</td>
                            <td style="padding: 0.5rem; text-align: right;">{{ $ligne['traitees'] }}</td>
                            <td style="padding: 0.5rem; text-align: right; white-space: nowrap; {{ $couleurDelai($ligne['evolution_delai']) }}">{{ $delai($ligne['delai']) }}</td>
                            <td style="padding: 0.5rem; text-align: right; white-space: nowrap;">{{ $delai($ligne['delai_avant']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 1rem; text-align: center; opacity: 0.7;">Aucune demande sur cette période : choisissez une période plus large.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p style="margin-top: 0.75rem; font-size: 0.75rem; opacity: 0.7;">
            Délai moyen : du dépôt à la réponse, sur les demandes traitées ou refusées pendant la période. « Traitées » : demandes de la période ayant déjà reçu une réponse.
        </p>
    </x-filament::section>

    {{-- Signalements par quartier --}}
    <x-filament::section heading="Signalements par quartier" description="Quartier de résidence de l’habitant qui a signalé le problème.">
        @if ($quartiers->isEmpty())
            <p style="font-size: 0.875rem; opacity: 0.7;">Aucun signalement sur cette période.</p>
        @else
            <div style="display: grid; gap: 0.6rem;" role="list" aria-label="Signalements par quartier">
                @foreach ($quartiers as $quartier)
                    <div role="listitem">
                        <div style="display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.85rem;">
                            <span style="font-weight: 600;">{{ $quartier['nom'] }}</span>
                            <span style="white-space: nowrap; opacity: 0.8;">{{ $quartier['signalements'] }} signalement(s) · {{ $quartier['ouverts'] }} ouvert(s) · {{ $quartier['avant'] }} auparavant</span>
                        </div>
                        <div style="height: 0.75rem; margin-top: 0.25rem; border-radius: 9999px; overflow: hidden; background: rgba(127,127,127,0.12);">
                            <div style="height: 100%; width: {{ round($quartier['signalements'] * 100 / $maxQuartier, 1) }}%; background: rgb(37,99,235);"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
