<?php

use App\Models\Demarche;
use App\Models\Signalement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::agent'), Title('Espace agent — Tableau de bord')] class extends Component {
    /** Heure de référence des agents pour « aujourd'hui » et les jours du graphique (l'application stocke en UTC). */
    public const FUSEAU_AFFICHAGE = 'Indian/Antananarivo';

    /** Nombre de jours affichés dans le graphique d'activité. */
    public const JOURS_GRAPHIQUE = 7;

    public function mount(): void
    {
        Gate::authorize('viewAgentSpace');
    }

    /**
     * Début de la journée en cours, à l'heure locale des agents.
     */
    protected function debutAujourdhui(): CarbonImmutable
    {
        return now(self::FUSEAU_AFFICHAGE)->toImmutable()->startOfDay();
    }

    /**
     * Nombre de demandes par état.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function parStatut(): array
    {
        Gate::authorize('viewAgentSpace');

        $totaux = Demarche::query()->visibleTo(auth()->user())->selectRaw('statut, count(*) as total')->groupBy('statut')->pluck('total', 'statut');

        return collect(Demarche::STATUT_OPTIONS)->mapWithKeys(fn (string $statut): array => [$statut => (int) ($totaux[$statut] ?? 0)])->all();
    }

    /**
     * F86 : urgences médicales ouvertes (déposées ou en cours) et, parmi elles, celles sans prise en charge.
     *
     * @return array{ouvertes: int, sans_prise_en_charge: int}
     */
    #[Computed]
    public function urgences(): array
    {
        Gate::authorize('viewAgentSpace');

        $ouvertes = Demarche::query()->visibleTo(auth()->user())->urgencesATraiter();

        return [
            'ouvertes' => (clone $ouvertes)->count(),
            'sans_prise_en_charge' => $ouvertes->whereNull('pris_en_charge_le')->count(),
        ];
    }

    /**
     * Compteurs clés du suivi quotidien.
     *
     * @return array{total: int, aujourdhui: int, en_attente: int, signalements: int, signalements_nouveaux: int}
     */
    #[Computed]
    public function compteurs(): array
    {
        Gate::authorize('viewAgentSpace');

        $signalements = Signalement::query()->selectRaw('statut, count(*) as total')->groupBy('statut')->pluck('total', 'statut');

        return [
            'total' => array_sum($this->parStatut),
            'aujourdhui' => Demarche::query()->visibleTo(auth()->user())->where('created_at', '>=', $this->debutAujourdhui()->utc())->count(),
            'en_attente' => $this->parStatut['deposee'] ?? 0,
            'signalements' => (int) $signalements->sum(),
            'signalements_nouveaux' => (int) ($signalements['nouveau'] ?? 0),
        ];
    }

    /**
     * Demandes déposées par jour sur les 7 derniers jours (aujourd'hui inclus), du plus ancien au plus récent.
     *
     * @return list<array{date: string, jour: string, total: int}>
     */
    #[Computed]
    public function activite(): array
    {
        Gate::authorize('viewAgentSpace');

        $debut = $this->debutAujourdhui()->subDays(self::JOURS_GRAPHIQUE - 1);

        // Regroupement en PHP : le jour est calculé à l'heure locale, identique sur SQLite et MariaDB.
        $parJour = Demarche::query()
            ->visibleTo(auth()->user())
            ->where('created_at', '>=', $debut->copy()->utc())
            ->pluck('created_at')
            ->countBy(fn (mixed $date): string => CarbonImmutable::parse($date)->timezone(self::FUSEAU_AFFICHAGE)->toDateString());

        $jours = [];

        for ($i = 0; $i < self::JOURS_GRAPHIQUE; $i++) {
            $jour = $debut->copy()->addDays($i);
            $jours[] = [
                'date' => $jour->toDateString(),
                'jour' => $i === self::JOURS_GRAPHIQUE - 1 ? 'Auj.' : $jour->locale('fr')->isoFormat('ddd D'),
                'total' => (int) ($parJour[$jour->toDateString()] ?? 0),
            ];
        }

        return $jours;
    }

    /**
     * @return Collection<int, Demarche>
     */
    #[Computed]
    public function dernieresDemandes(): Collection
    {
        Gate::authorize('viewAgentSpace');

        return Demarche::query()->visibleTo(auth()->user())->with(['user:id,name', 'service:id,nom'])->latest()->latest('id')->limit(6)->get();
    }

    /**
     * @return Collection<int, Signalement>
     */
    #[Computed]
    public function derniersSignalements(): Collection
    {
        Gate::authorize('viewAgentSpace');

        return Signalement::query()->latest()->latest('id')->limit(4)->get();
    }
}; ?>

@php
    $compteurs = $this->compteurs;
    $activite = $this->activite;
    $maxJour = max(1, ...array_column($activite, 'total'));
    $totalSemaine = array_sum(array_column($activite, 'total'));
@endphp

<section class="mx-auto w-full max-w-6xl space-y-6" wire:poll.{{ \App\Support\ModeDegrade::poll(60) }}.visible>
    <x-tn.page-header
        label="Espace agent"
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Tableau de bord' => null]"
        title="Tableau de bord"
        subtitle="L’activité de la plateforme en un coup d’œil : demandes, prises en charge et signalements."
    >
        <x-slot:actions>
            <flux:button :href="route('agent.demandes', ['enAttente' => 1])" variant="primary" icon="clipboard-document-list" wire:navigate>
                Traiter les demandes
            </flux:button>
        </x-slot:actions>
    </x-tn.page-header>

    {{-- F86 : urgences médicales à traiter en priorité --}}
    @php($urgences = $this->urgences)
    <a
        href="{{ route('agent.demandes', ['prioritaires' => 1]) }}"
        wire:navigate
        data-test="compteur-urgences"
        @class([
            'flex items-center justify-between gap-3 rounded-md border p-4 transition',
            'border-magenta bg-magenta/8 hover:bg-magenta/12' => $urgences['ouvertes'] > 0,
            'border-line hover:border-magenta/50' => $urgences['ouvertes'] === 0,
        ])
    >
        <span class="flex items-center gap-3">
            <flux:icon name="heart" @class(['size-6 shrink-0', 'text-magenta' => $urgences['ouvertes'] > 0, 'text-ink-2' => $urgences['ouvertes'] === 0]) aria-hidden="true" />
            <span>
                <span class="block font-semibold text-ink">Urgences médicales à traiter en priorité</span>
                <span @class(['block text-sm', 'font-medium text-magenta' => $urgences['sans_prise_en_charge'] > 0, 'text-ink-2' => $urgences['sans_prise_en_charge'] === 0])>
                    {{ $urgences['sans_prise_en_charge'] > 0 ? $urgences['sans_prise_en_charge'].' sans prise en charge' : 'Toutes prises en charge' }}
                </span>
            </span>
        </span>
        <span @class(['tn-display text-3xl font-semibold', 'text-magenta' => $urgences['ouvertes'] > 0, 'text-ink-2' => $urgences['ouvertes'] === 0])>{{ $urgences['ouvertes'] }}</span>
    </a>

    {{-- Compteurs du suivi quotidien --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" data-test="compteurs">
        <a href="{{ route('agent.demandes') }}" class="rounded-md border border-line bg-surface p-4 transition hover:border-cyan" wire:navigate>
            <span class="text-sm text-ink-2">Nouvelles aujourd’hui</span>
            <span class="tn-display mt-1 block text-3xl font-semibold text-ink" data-test="compteur-aujourdhui">{{ $compteurs['aujourdhui'] }}</span>
            <span class="text-xs text-ink-2">demande(s) déposée(s)</span>
        </a>
        <a href="{{ route('agent.demandes', ['filterStatut' => 'deposee']) }}" @class(['rounded-md border p-4 transition hover:border-cyan', 'border-amber/50 bg-amber/5' => $compteurs['en_attente'] > 0, 'border-line' => $compteurs['en_attente'] === 0]) wire:navigate>
            <span class="text-sm text-ink-2">En attente de prise en charge</span>
            <span class="tn-display mt-1 block text-3xl font-semibold text-ink" data-test="compteur-en-attente">{{ $compteurs['en_attente'] }}</span>
            @if ($compteurs['en_attente'] > 0)
                <span class="text-xs text-amber">Action attendue</span>
            @else
                <span class="text-xs text-green">Rien en attente</span>
            @endif
        </a>
        <a href="{{ route('agent.demandes') }}" class="rounded-md border border-line bg-surface p-4 transition hover:border-cyan" wire:navigate>
            <span class="text-sm text-ink-2">Demandes au total</span>
            <span class="tn-display mt-1 block text-3xl font-semibold text-ink" data-test="compteur-total">{{ $compteurs['total'] }}</span>
            <span class="text-xs text-ink-2">{{ $totalSemaine }} sur 7 jours</span>
        </a>
        <a href="{{ route('signalements.index') }}" class="rounded-md border border-line bg-surface p-4 transition hover:border-cyan" wire:navigate>
            <span class="text-sm text-ink-2">Signalements</span>
            <span class="tn-display mt-1 block text-3xl font-semibold text-ink" data-test="compteur-signalements">{{ $compteurs['signalements'] }}</span>
            <span @class(['text-xs', 'text-magenta' => $compteurs['signalements_nouveaux'] > 0, 'text-ink-2' => $compteurs['signalements_nouveaux'] === 0])>
                dont {{ $compteurs['signalements_nouveaux'] }} nouveau(x)
            </span>
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- Graphique : demandes par jour sur 7 jours --}}
        <x-tn.panel label="Demandes par jour — 7 derniers jours" class="lg:col-span-3">
            @if ($totalSemaine === 0)
                <x-tn.empty icon="chart-bar" title="Aucune demande cette semaine" text="Les nouvelles demandes des habitants apparaîtront ici jour par jour." />
            @else
                <figure>
                    <div class="flex h-48 items-end gap-2 sm:gap-3" aria-hidden="true">
                        @foreach ($activite as $jour)
                            <div wire:key="jour-{{ $jour['date'] }}" class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                                <span class="font-mono text-xs text-ink">{{ $jour['total'] }}</span>
                                <div
                                    @class(['w-full max-w-12 rounded-t-xs', 'bg-cyan' => $loop->last, 'bg-cyan/45' => ! $loop->last])
                                    style="height: {{ $jour['total'] > 0 ? max(4, (int) round($jour['total'] / $maxJour * 100)) : 0 }}%"
                                ></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex gap-2 border-t border-line pt-2 sm:gap-3" aria-hidden="true">
                        @foreach ($activite as $jour)
                            <span class="flex-1 text-center font-mono text-[0.6875rem] text-ink-2">{{ $jour['jour'] }}</span>
                        @endforeach
                    </div>
                    <figcaption class="mt-3 text-sm text-ink-2">{{ $totalSemaine }} demande(s) déposée(s) sur les 7 derniers jours.</figcaption>

                    {{-- Version texte du graphique pour les lecteurs d'écran --}}
                    <div class="sr-only">
                        <table>
                            <caption>Demandes déposées par jour</caption>
                            <thead><tr><th scope="col">Jour</th><th scope="col">Demandes</th></tr></thead>
                            <tbody>
                                @foreach ($activite as $jour)
                                    <tr><td>{{ $jour['date'] }}</td><td>{{ $jour['total'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </figure>
            @endif
        </x-tn.panel>

        {{-- Répartition par état --}}
        <x-tn.panel label="Demandes par état" class="lg:col-span-2">
            <ul class="space-y-3">
                @foreach ($this->parStatut as $statut => $total)
                    <li wire:key="statut-{{ $statut }}">
                        <a href="{{ route('agent.demandes', ['filterStatut' => $statut]) }}" class="block rounded-xs hover:bg-surface-2" wire:navigate>
                            <div class="flex items-center justify-between gap-2">
                                <x-tn.status-badge :etat="Demarche::STATUT_ETATS[$statut]">{{ Demarche::libelleStatut($statut) }}</x-tn.status-badge>
                                <span class="tn-display text-lg font-semibold text-ink">{{ $total }}</span>
                            </div>
                            <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-surface-2" aria-hidden="true">
                                <div class="h-full rounded-full bg-cyan/60" style="width: {{ $compteurs['total'] > 0 ? (int) round($total / $compteurs['total'] * 100) : 0 }}%"></div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-tn.panel>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- Dernières demandes avec lien direct --}}
        <div class="min-w-0 space-y-3 lg:col-span-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <x-tn.section-label as="h2">Dernières demandes</x-tn.section-label>
                <flux:link :href="route('agent.demandes')" wire:navigate class="text-sm">Toutes les demandes</flux:link>
            </div>

            @if ($this->dernieresDemandes->isEmpty())
                <x-tn.empty icon="inbox" title="Aucune demande pour l’instant" text="Les habitants n’ont encore déposé aucune demande." />
            @else
                <ul class="divide-y divide-line rounded-md border border-line bg-surface">
                    @foreach ($this->dernieresDemandes as $demande)
                        <li wire:key="demande-{{ $demande->id }}">
                            <a href="{{ route('demarches.show', $demande) }}" class="flex flex-col gap-1 p-4 transition hover:bg-surface-2 sm:flex-row sm:items-center sm:justify-between" wire:navigate>
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-ink">{{ $demande->titre }}</p>
                                    <p class="truncate text-sm text-ink-2">
                                        {{ $demande->user?->name ?? 'Habitant inconnu' }} · {{ $demande->service?->nom ?? 'Service non précisé' }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="font-mono text-xs text-ink-2" title="{{ $demande->created_at->timezone($this::FUSEAU_AFFICHAGE)->format('d/m/Y à H:i') }}">{{ $demande->created_at->locale('fr')->diffForHumans() }}</span>
                                    <x-tn.status-badge :etat="$demande->etatStatut()">{{ Demarche::libelleStatut($demande->statut) }}</x-tn.status-badge>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Derniers signalements --}}
        <div class="min-w-0 space-y-3 lg:col-span-2">
            <div class="flex items-center justify-between gap-2">
                <x-tn.section-label as="h2">Derniers signalements</x-tn.section-label>
                <flux:link :href="route('signalements.index')" wire:navigate class="text-sm">Tous les signalements</flux:link>
            </div>

            @if ($this->derniersSignalements->isEmpty())
                <x-tn.empty icon="exclamation-triangle" title="Aucun signalement" text="Aucun problème n’a été signalé dans l’espace public." />
            @else
                <ul class="divide-y divide-line rounded-md border border-line bg-surface">
                    @foreach ($this->derniersSignalements as $signalement)
                        <li wire:key="signalement-{{ $signalement->id }}">
                            <a href="{{ route('signalements.show', $signalement) }}" class="block p-4 transition hover:bg-surface-2" wire:navigate>
                                <div class="flex items-center justify-between gap-2">
                                    <p class="min-w-0 truncate font-medium text-ink">{{ Signalement::libelleCategorie($signalement->categorie) }}</p>
                                    <x-tn.status-badge :etat="$signalement->etatStatut()">{{ Signalement::libelleStatut($signalement->statut) }}</x-tn.status-badge>
                                </div>
                                <p class="truncate text-sm text-ink-2">{{ $signalement->lieu }}</p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</section>
