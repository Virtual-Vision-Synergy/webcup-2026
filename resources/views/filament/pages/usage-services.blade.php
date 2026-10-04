<x-filament-panels::page>
    @php
        $stats = $this->statistiques;
        $classement = $this->classement;
        $totaux = $stats->totaux($classement);
        $analyses = $stats->analyses($classement);
        $evolution = $stats->evolutionQuotidienne();
        $maxEvolution = max(1, collect($evolution)->max('consultations'), collect($evolution)->max('lancees'));
        $top = $classement->take(10);
        $maxBarre = max(1, (int) $top->max(fn ($l) => $l['consultations'] + $l['lancees']));
        $couleurs = ['succes' => 'success', 'info' => 'info', 'attention' => 'warning', 'alerte' => 'danger'];
        $icones = ['succes' => 'heroicon-o-trophy', 'info' => 'heroicon-o-information-circle', 'attention' => 'heroicon-o-arrow-trending-up', 'alerte' => 'heroicon-o-exclamation-triangle'];
        $carte = 'padding: 1rem; border-radius: 0.75rem; border: 1px solid rgba(127,127,127,0.25);';
    @endphp

    {{-- Styles en ligne : le CSS précompilé de Filament ne contient pas les utilitaires Tailwind du site. --}}

    {{-- Filtres --}}
    <x-filament::section>
        <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));">
            <label style="display: grid; gap: 0.25rem; font-size: 0.875rem;">
                <span style="font-weight: 600;">Période</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="periode">
                        @foreach (\App\Services\UsageServices::PERIODES as $jours => $libelle)
                            <option value="{{ $jours }}">{{ $libelle }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>

            <label style="display: grid; gap: 0.25rem; font-size: 0.875rem;">
                <span style="font-weight: 600;">Secteur (quartier des habitants)</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="quartier">
                        <option value="">Tous les quartiers</option>
                        @foreach ($this->quartiers() as $id => $nom)
                            <option value="{{ $id }}">{{ $nom }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>

            <label style="display: grid; gap: 0.25rem; font-size: 0.875rem;">
                <span style="font-weight: 600;">Catégorie de service</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="categorie">
                        <option value="">Toutes les catégories</option>
                        @foreach ($this->categories() as $valeur => $libelle)
                            <option value="{{ $valeur }}">{{ $libelle }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
        </div>

        <p style="margin-top: 0.75rem; font-size: 0.8rem; opacity: 0.7;">
            Du {{ $stats->debut->format('d/m/Y') }} au {{ $stats->fin->format('d/m/Y') }}, comparé au
            {{ $stats->debutPrecedent->format('d/m/Y') }} – {{ $stats->finPrecedente->format('d/m/Y') }}.
            Consultations comptées de façon anonyme (une par visiteur, par service et par jour, sans donnée personnelle).
        </p>
        <p wire:loading style="margin-top: 0.5rem; font-size: 0.8rem; font-weight: 600;">Mise à jour des statistiques…</p>
    </x-filament::section>

    {{-- Chiffres clés --}}
    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));">
        @foreach ([
            ['Consultations', $totaux['consultations'], null],
            ['Démarches lancées', $totaux['lancees'], null],
            ['Démarches terminées', $totaux['terminees'], null],
            ['Taux d’abandon', $totaux['taux_abandon'] === null ? '—' : $totaux['taux_abandon'].' %', null],
            ['Évolution', $totaux['evolution'] === null ? '—' : \App\Services\UsageServices::pourcentage($totaux['evolution']), $totaux['evolution']],
        ] as [$libelle, $valeur, $tendance])
            <div style="{{ $carte }}">
                <div style="font-size: 0.8rem; opacity: 0.7;">{{ $libelle }}</div>
                <div style="font-size: 1.5rem; font-weight: 700; {{ $tendance === null ? '' : ($tendance >= 0 ? 'color: rgb(22,163,74);' : 'color: rgb(220,38,38);') }}">{{ $valeur }}</div>
            </div>
        @endforeach
    </div>

    {{-- Analyses générées par règles --}}
    <x-filament::section heading="Ce qu’il faut retenir" description="Phrases générées automatiquement à partir des chiffres (règles, sans IA).">
        <div style="display: grid; gap: 0.75rem;">
            @foreach ($analyses as $analyse)
                <x-filament::callout
                    :color="$couleurs[$analyse['niveau']] ?? 'info'"
                    :icon="$icones[$analyse['niveau']] ?? 'heroicon-o-information-circle'"
                    :description="$analyse['texte']"
                />
            @endforeach
        </div>
    </x-filament::section>

    {{-- Graphique en barres --}}
    <x-filament::section heading="Services les plus utilisés" description="Top 10 : consultations (clair) et démarches lancées (foncé).">
        @if ($top->sum(fn ($l) => $l['consultations'] + $l['lancees']) === 0)
            <p style="font-size: 0.875rem; opacity: 0.7;">Aucune donnée sur cette période : élargissez la période ou retirez un filtre.</p>
        @else
            <div style="display: grid; gap: 0.6rem;" role="list" aria-label="Classement des services">
                @foreach ($top as $ligne)
                    <div role="listitem">
                        <div style="display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.85rem;">
                            <span style="font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $loop->iteration }}. {{ $ligne['nom'] }}</span>
                            <span style="white-space: nowrap; opacity: 0.8;">{{ $ligne['consultations'] }} vues · {{ $ligne['lancees'] }} démarches</span>
                        </div>
                        <div style="display: flex; height: 0.75rem; margin-top: 0.25rem; border-radius: 9999px; overflow: hidden; background: rgba(127,127,127,0.12);">
                            <div style="width: {{ round($ligne['lancees'] * 100 / $maxBarre, 1) }}%; background: rgb(37,99,235);"></div>
                            <div style="width: {{ round($ligne['consultations'] * 100 / $maxBarre, 1) }}%; background: rgba(37,99,235,0.35);"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    {{-- Évolution --}}
    <x-filament::section heading="Évolution sur la période" description="Par jour : consultations (bleu clair) et démarches lancées (orange).">
        <svg viewBox="0 -5 600 175" preserveAspectRatio="none" style="width: 100%; height: 12rem;" role="img" aria-label="Évolution quotidienne des consultations et des démarches">
            <line x1="0" y1="160" x2="600" y2="160" stroke="currentColor" stroke-opacity="0.2" />
            <line x1="0" y1="10" x2="600" y2="10" stroke="currentColor" stroke-opacity="0.1" stroke-dasharray="4" />
            <polyline fill="none" stroke="rgb(96,165,250)" stroke-width="2.5" vector-effect="non-scaling-stroke" points="{{ $this->polyligne($evolution, 'consultations', $maxEvolution) }}" />
            <polyline fill="none" stroke="rgb(234,88,12)" stroke-width="2.5" vector-effect="non-scaling-stroke" points="{{ $this->polyligne($evolution, 'lancees', $maxEvolution) }}" />
        </svg>
        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; opacity: 0.7;">
            <span>{{ $evolution[0]['libelle'] ?? '' }}</span>
            <span>max. {{ $maxEvolution }} / jour</span>
            <span>{{ $evolution[count($evolution) - 1]['libelle'] ?? '' }}</span>
        </div>
    </x-filament::section>

    {{-- Tableau détaillé --}}
    <x-filament::section heading="Classement détaillé">
        <div style="overflow-x: auto;">
            <table style="width: 100%; font-size: 0.85rem; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid rgba(127,127,127,0.3);">
                        <th style="padding: 0.5rem;">#</th>
                        <th style="padding: 0.5rem;">Service</th>
                        <th style="padding: 0.5rem;">Catégorie</th>
                        <th style="padding: 0.5rem; text-align: right;">Consultations</th>
                        <th style="padding: 0.5rem; text-align: right;">Lancées</th>
                        <th style="padding: 0.5rem; text-align: right;">Terminées</th>
                        <th style="padding: 0.5rem; text-align: right;">Abandon</th>
                        <th style="padding: 0.5rem; text-align: right;">Évolution</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($classement as $ligne)
                        <tr wire:key="usage-{{ $ligne['service_id'] }}" style="border-bottom: 1px solid rgba(127,127,127,0.12);">
                            <td style="padding: 0.5rem;">{{ $loop->iteration }}</td>
                            <td style="padding: 0.5rem; font-weight: 600;">{{ $ligne['nom'] }}</td>
                            <td style="padding: 0.5rem;">{{ \App\Models\Service::labelCategorie($ligne['categorie']) }}</td>
                            <td style="padding: 0.5rem; text-align: right;">{{ $ligne['consultations'] }}</td>
                            <td style="padding: 0.5rem; text-align: right;">{{ $ligne['lancees'] }}</td>
                            <td style="padding: 0.5rem; text-align: right;">{{ $ligne['terminees'] }}</td>
                            <td style="padding: 0.5rem; text-align: right; {{ ($ligne['taux_abandon'] ?? 0) >= \App\Services\UsageServices::SEUIL_ABANDON ? 'color: rgb(220,38,38); font-weight: 600;' : '' }}">
                                {{ $ligne['taux_abandon'] === null ? '—' : $ligne['taux_abandon'].' %' }}
                            </td>
                            <td style="padding: 0.5rem; text-align: right; {{ $ligne['evolution'] === null ? '' : ($ligne['evolution'] >= 0 ? 'color: rgb(22,163,74);' : 'color: rgb(220,38,38);') }}">
                                {{ $ligne['evolution'] === null ? '—' : \App\Services\UsageServices::pourcentage($ligne['evolution']) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="padding: 1rem; text-align: center; opacity: 0.7;">Aucun service dans cette catégorie.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p style="margin-top: 0.75rem; font-size: 0.75rem; opacity: 0.7;">
            « Abandon » : démarches refusées ou restées « déposées » plus de {{ \App\Services\UsageServices::JOURS_AVANT_ABANDON }} jours sans prise en charge.
        </p>
    </x-filament::section>
</x-filament-panels::page>
