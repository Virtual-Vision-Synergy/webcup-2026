<?php

use App\Models\Demarche;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::agent'), Title('Espace agent — Demandes des habitants')] class extends Component {
    use WithPagination;

    /** États qui attendent une action d'un agent. */
    public const STATUTS_EN_ATTENTE = ['deposee', 'en_cours'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $filterStatut = '';

    #[Url(except: false)]
    public bool $enAttente = false;

    /** F84 : uniquement les demandes dont le dernier message n'est pas une réponse d'agent. */
    #[Url(except: false)]
    public bool $sansReponse = false;

    public function mount(): void
    {
        Gate::authorize('viewAgentSpace');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatut(): void
    {
        $this->resetPage();
    }

    public function updatedEnAttente(): void
    {
        $this->resetPage();
    }

    public function updatedSansReponse(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        Gate::authorize('viewAgentSpace');

        $this->reset('search', 'filterStatut', 'enAttente', 'sansReponse');
        $this->resetPage();
    }

    /**
     * @return Builder<Demarche>
     */
    protected function filteredQuery(): Builder
    {
        // F70 : uniquement les démarches des services de l'agent (toutes pour l'admin).
        return Demarche::query()
            ->visibleTo(auth()->user())
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($q) => $q->where('titre', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->when($this->enAttente, fn ($query) => $query->whereIn('statut', self::STATUTS_EN_ATTENTE))
            ->when($this->sansReponse, fn ($query) => $query->sansReponse())
            ->when($this->filterStatut !== '', fn ($query) => $query->where('statut', $this->filterStatut));
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        Gate::authorize('viewAgentSpace');

        return $this->filteredQuery()
            ->with(['user:id,name', 'service:id,nom', 'derniereReponse'])
            ->orderByRaw("case when statut in ('deposee', 'en_cours') then 0 else 1 end")
            ->oldest()
            ->paginate(15);
    }

    /**
     * Nombre de demandes par statut (compteurs en tête de page).
     *
     * @return array<string, int>
     */
    #[Computed]
    public function compteurs(): array
    {
        Gate::authorize('viewAgentSpace');

        $parStatut = Demarche::query()->visibleTo(auth()->user())->selectRaw('statut, count(*) as total')->groupBy('statut')->pluck('total', 'statut');

        return collect(Demarche::STATUT_OPTIONS)->mapWithKeys(fn (string $statut): array => [$statut => (int) ($parStatut[$statut] ?? 0)])->all();
    }

    public function changerStatut(int $id, string $statut): void
    {
        $record = Demarche::findOrFail($id);
        AuditLogger::autoriser('changerStatut', $record);
        abort_unless(in_array($statut, Demarche::STATUT_OPTIONS, true), 422);

        $record->changerStatut($statut);
        unset($this->items, $this->compteurs);

        Flux::toast(variant: 'success', text: 'Statut mis à jour : '.Demarche::libelleStatut($statut).'.');
    }
}; ?>

@php
    $enAttenteTotal = $this->compteurs['deposee'] + $this->compteurs['en_cours'];
@endphp

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        label="Espace agent"
        :breadcrumb="['Espace agent' => route('agent.index'), 'Demandes des habitants' => null]"
        title="Demandes des habitants"
        :subtitle="$enAttenteTotal.' demande(s) en attente d’une action sur '.array_sum($this->compteurs).' au total'"
    />

    {{-- Compteurs par état --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach (Demarche::STATUT_OPTIONS as $statut)
            <button
                type="button"
                wire:click="$set('filterStatut', '{{ $filterStatut === $statut ? '' : $statut }}')"
                aria-pressed="{{ $filterStatut === $statut ? 'true' : 'false' }}"
                @class(['rounded-md border p-4 text-start transition hover:border-cyan', 'border-cyan ring-2 ring-cyan' => $filterStatut === $statut, 'border-line' => $filterStatut !== $statut])
            >
                <x-tn.status-badge :etat="Demarche::STATUT_ETATS[$statut]">{{ Demarche::libelleStatut($statut) }}</x-tn.status-badge>
                <span class="tn-display mt-2 block text-2xl font-semibold text-ink">{{ $this->compteurs[$statut] }}</span>
                @if (in_array($statut, $this::STATUTS_EN_ATTENTE, true))
                    <span class="inline-flex items-center gap-1 text-xs text-amber"><flux:icon.exclamation-triangle variant="micro" class="size-3.5" aria-hidden="true" />Action attendue</span>
                @else
                    <span class="inline-flex items-center gap-1 text-xs text-ink-2"><flux:icon.check variant="micro" class="size-3.5" aria-hidden="true" />Clôturée</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Filtres --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Rechercher…" aria-label="Rechercher une demande" class="sm:max-w-xs" />
        <flux:select wire:model.live="filterStatut" aria-label="Filtrer par état" class="sm:max-w-52">
            <flux:select.option value="">État : tous</flux:select.option>
            @foreach (Demarche::STATUT_OPTIONS as $option)
                <flux:select.option :value="$option">{{ Demarche::libelleStatut($option) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:checkbox wire:model.live="enAttente" label="En attente d’action uniquement" />
        <flux:checkbox wire:model.live="sansReponse" label="Sans réponse uniquement" />
        @if ($search !== '' || $filterStatut !== '' || $enAttente || $sansReponse)
            <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="resetFilters">Effacer les filtres</flux:button>
        @endif
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">Mise à jour…</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="inbox" title="Aucune demande à afficher" :text="($search !== '' || $filterStatut !== '' || $enAttente || $sansReponse) ? 'Aucune demande ne correspond aux filtres choisis.' : 'Aucune demande pour les services auxquels vous êtes rattaché.'" />
    @else
        <ul class="space-y-3">
            @foreach ($this->items as $item)
                @php($attente = in_array($item->statut, $this::STATUTS_EN_ATTENTE, true))
                <li wire:key="demande-{{ $item->id }}" @class(['rounded-md border p-4', 'border-amber/50 bg-amber/5' => $attente, 'border-line' => ! $attente])>
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-tn.status-badge :etat="$item->etatStatut()">{{ Demarche::libelleStatut($item->statut) }}</x-tn.status-badge>
                                @if ($attente)
                                    <x-tn.status-badge etat="perturbe">Action attendue</x-tn.status-badge>
                                @endif
                                @if ($item->reponseEnvoyee())
                                    <x-tn.status-badge etat="normal">Réponse envoyée</x-tn.status-badge>
                                @else
                                    <x-tn.status-badge etat="info">En attente de réponse</x-tn.status-badge>
                                @endif
                            </div>
                            <a href="{{ route('demarches.show', $item) }}" class="block font-medium text-ink hover:text-cyan">{{ $item->titre }}</a>
                            @if ($item->derniereReponse)
                                <p class="text-xs text-ink-2">Dernier message le <span class="font-mono">{{ $item->derniereReponse->created_at->format('d.m.Y · H:i') }}</span></p>
                            @endif
                            <p class="text-sm text-ink-2">
                                {{ $item->user?->name ?? 'Habitant inconnu' }} · {{ $item->service?->nom ?? 'Service non précisé' }} ·
                                <span class="font-mono text-xs">Déposée le {{ $item->created_at->format('d.m.Y') }}</span>
                            </p>
                            @if ($item->description)
                                <p class="text-sm text-ink-2">{{ Str::limit($item->description, 160) }}</p>
                            @endif
                        </div>

                        <div class="flex flex-col gap-2 md:shrink-0 md:items-end">
                        @can('repondre', $item)
                            <flux:button size="xs" variant="primary" icon="chat-bubble-left-right" :href="route('demarches.show', $item)" wire:navigate>Répondre</flux:button>
                        @endcan
                        @can('changerStatut', $item)
                            <div class="flex flex-wrap gap-1 md:justify-end" role="group" aria-label="Changer l’état de la demande">
                                @foreach (Demarche::STATUT_OPTIONS as $option)
                                    <flux:button
                                        size="xs"
                                        wire:click="changerStatut({{ $item->id }}, '{{ $option }}')"
                                        :disabled="$option === $item->statut"
                                        :variant="$option === $item->statut ? 'primary' : 'outline'"
                                    >
                                        {{ Demarche::libelleStatut($option) }}
                                    </flux:button>
                                @endforeach
                            </div>
                        @endcan
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        {{ $this->items->links() }}
    @endif
</section>
