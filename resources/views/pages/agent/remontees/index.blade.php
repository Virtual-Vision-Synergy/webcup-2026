<?php

use App\Models\Remontee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::agent'), Title('Espace agent — Remontées sur les données')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $filterStatut = '';

    #[Url(except: '')]
    public string $filterCategorie = '';

    public function mount(): void
    {
        Gate::authorize('viewAgentSpace');
    }

    public function updatedFilterStatut(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCategorie(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        Gate::authorize('viewAgentSpace');

        $this->reset('filterStatut', 'filterCategorie');
        $this->resetPage();
    }

    /**
     * Non traitées d'abord, puis les plus anciennes : personne n'attend indéfiniment.
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        Gate::authorize('viewAgentSpace');

        return Remontee::query()
            ->with('user:id,name')
            ->when(in_array($this->filterStatut, Remontee::STATUT_OPTIONS, true), fn ($query) => $query->where('statut', $this->filterStatut))
            ->when(in_array($this->filterCategorie, Remontee::CATEGORIE_OPTIONS, true), fn ($query) => $query->where('categorie', $this->filterCategorie))
            ->orderByRaw("case when statut in ('recue', 'prise_en_compte') then 0 else 1 end")
            ->oldest('envoyee_le')
            ->oldest('id')
            ->paginate(15);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function compteurs(): array
    {
        Gate::authorize('viewAgentSpace');

        $parStatut = Remontee::query()->selectRaw('statut, count(*) as total')->groupBy('statut')->pluck('total', 'statut');

        return collect(Remontee::STATUT_OPTIONS)->mapWithKeys(fn (string $statut): array => [$statut => (int) ($parStatut[$statut] ?? 0)])->all();
    }
}; ?>

@php
    $enAttente = $this->compteurs['recue'] + $this->compteurs['prise_en_compte'];
    $filtre = $filterStatut !== '' || $filterCategorie !== '';
@endphp

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        label="Espace agent"
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Remontées sur les données' => null]"
        title="Remontées sur les données"
        :subtitle="$enAttente.' remontée(s) en attente de traitement sur '.array_sum($this->compteurs).' au total'"
    />

    {{-- Compteurs par état --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach (Remontee::STATUT_OPTIONS as $statut)
            <button
                type="button"
                wire:click="$set('filterStatut', '{{ $filterStatut === $statut ? '' : $statut }}')"
                aria-pressed="{{ $filterStatut === $statut ? 'true' : 'false' }}"
                @class(['rounded-md border p-4 text-start transition hover:border-cyan', 'border-cyan ring-2 ring-cyan' => $filterStatut === $statut, 'border-line' => $filterStatut !== $statut])
            >
                <x-tn.status-badge :etat="Remontee::STATUT_ETATS[$statut]">{{ Remontee::libelleStatut($statut) }}</x-tn.status-badge>
                <span class="tn-display mt-2 block text-2xl font-semibold text-ink">{{ $this->compteurs[$statut] }}</span>
            </button>
        @endforeach
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <flux:select wire:model.live="filterStatut" aria-label="Filtrer par état" class="sm:max-w-52">
            <flux:select.option value="">État : tous</flux:select.option>
            @foreach (Remontee::STATUT_OPTIONS as $option)
                <flux:select.option :value="$option">{{ Remontee::libelleStatut($option) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterCategorie" aria-label="Filtrer par sujet" class="sm:max-w-72">
            <flux:select.option value="">Sujet : tous</flux:select.option>
            @foreach (Remontee::CATEGORIE_LABELS as $valeur => $libelle)
                <flux:select.option :value="$valeur">{{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>
        @if ($filtre)
            <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="resetFilters">Effacer les filtres</flux:button>
        @endif
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">Mise à jour…</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="inbox" title="Aucune remontée à afficher" :text="$filtre ? 'Aucune remontée ne correspond aux filtres choisis.' : 'Les habitants n’ont encore fait remonter aucune inquiétude.'" />
    @else
        <ul class="space-y-3">
            @foreach ($this->items as $item)
                @php($attente = in_array($item->statut, Remontee::STATUTS_EN_ATTENTE, true))
                <li wire:key="remontee-{{ $item->id }}" @class(['rounded-md border p-4', 'border-amber/50 bg-amber/5' => $attente, 'border-line' => ! $attente])>
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-tn.status-badge :etat="$item->etatStatut()">{{ Remontee::libelleStatut($item->statut) }}</x-tn.status-badge>
                            <span class="font-mono text-xs text-ink-2">{{ $item->reference }}</span>
                        </div>
                        <a href="{{ route('agent.concerns.show', $item) }}" class="block font-medium text-ink hover:text-cyan">{{ $item->objet }}</a>
                        <p class="text-sm text-ink-2">
                            {{ $item->user?->name ?? 'Compte supprimé' }} · {{ Remontee::libelleCategorie($item->categorie) }} ·
                            <span class="font-mono text-xs">Envoyée le {{ Remontee::dateLocale($item->envoyee_le) }}</span>
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>

        {{ $this->items->links() }}
    @endif
</section>
