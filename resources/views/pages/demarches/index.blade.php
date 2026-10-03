<?php

use App\Models\Demarche;
use App\Models\Service;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Mes démarches')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $mine = false;

    #[Url(except: '')]
    public string $filterServiceId = '';

    #[Url(except: '')]
    public string $filterStatut = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Demarche::class);
    }

    /**
     * Agents et admins voient toutes les démarches ; un habitant ne voit que les siennes.
     */
    #[Computed]
    public function voitToutesLesDemarches(): bool
    {
        $user = auth()->user();

        return $user->isAdmin() || $user->isAgent();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMine(): void
    {
        $this->resetPage();
    }

    public function updatedFilterServiceId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatut(): void
    {
        $this->resetPage();
    }

    /**
     * Recherche et filtres, partagés par la liste (et la carte si l'entité a des coordonnées).
     *
     * @return Builder<Demarche>
     */
    protected function filteredQuery(): Builder
    {
        return Demarche::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($q) => $q->where('titre', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->when(! $this->voitToutesLesDemarches || $this->mine, fn ($query) => $query->whereBelongsTo(auth()->user()))
            ->when($this->filterServiceId !== '', fn ($query) => $query->where('service_id', $this->filterServiceId))
            ->when($this->filterStatut !== '', fn ($query) => $query->where('statut', $this->filterStatut));
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with(['user', 'service'])
            ->latest()
            ->paginate(10);
    }

    /**
     * Valeurs proposées dans la liste déroulante « Service ».
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function serviceOptions(): Collection
    {
        return Service::query()->orderBy('nom')->get(['id', 'nom']);
    }

    public function delete(int $id): void
    {
        $record = Demarche::findOrFail($id);
        $this->authorize('delete', $record);

        $record->delete();

        Flux::toast(variant: 'success', text: 'Démarche supprimée.');
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Mes démarches</flux:heading>
            <flux:text class="mt-1">{{ $this->items->total() }} démarche(s){{ $this->voitToutesLesDemarches ? ' au total' : '' }}</flux:text>
        </div>

        @can('create', \App\Models\Demarche::class)
            <flux:button variant="primary" icon="plus" :href="route('demarches.create')" wire:navigate>
                Nouvelle démarche
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Rechercher…" class="sm:max-w-xs" />
        <flux:select wire:model.live="filterServiceId" class="sm:max-w-52">
            <flux:select.option value="">Service : tous</flux:select.option>
            @foreach ($this->serviceOptions as $option)
                <flux:select.option :value="$option->id">{{ $option->nom }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterStatut" class="sm:max-w-52">
            <flux:select.option value="">Statut : tous</flux:select.option>
            @foreach (\App\Models\Demarche::STATUT_OPTIONS as $option)
                <flux:select.option :value="$option">{{ \App\Models\Demarche::libelleStatut($option) }}</flux:select.option>
            @endforeach
        </flux:select>
        @if ($this->voitToutesLesDemarches)
            <flux:checkbox wire:model.live="mine" label="Mes démarches uniquement" />
        @endif
    </div>

    @if ($this->items->isEmpty())
        <flux:card class="py-12 text-center">
            <flux:icon.document-text class="mx-auto size-10 text-zinc-400" />
            <flux:heading class="mt-2">Aucune démarche pour le moment</flux:heading>
            <flux:text class="mt-2">Modifiez les filtres ou déposez votre première démarche.</flux:text>
            <flux:button variant="primary" icon="plus" :href="route('demarches.create')" class="mt-4" wire:navigate>
                Nouvelle démarche
            </flux:button>
        </flux:card>
    @else
        <flux:table :paginate="$this->items">
            <flux:table.columns>
                <flux:table.column>Titre</flux:table.column>
                <flux:table.column>Service</flux:table.column>
                <flux:table.column>Statut</flux:table.column>
                @if ($this->voitToutesLesDemarches)
                    <flux:table.column>Auteur</flux:table.column>
                @endif
                <flux:table.column>Déposée le</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->items as $item)
                    <flux:table.row wire:key="row-{{ $item->id }}">
                        <flux:table.cell><flux:link :href="route('demarches.show', $item)" wire:navigate class="font-medium">{{ $item->titre }}</flux:link></flux:table.cell>
                        <flux:table.cell>{{ $item->service?->nom ?? '—' }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$item->couleurStatut()">{{ \App\Models\Demarche::libelleStatut($item->statut) }}</flux:badge></flux:table.cell>
                        @if ($this->voitToutesLesDemarches)
                            <flux:table.cell>{{ $item->user?->name }}</flux:table.cell>
                        @endif
                        <flux:table.cell>{{ $item->created_at->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye" :href="route('demarches.show', $item)" wire:navigate />
                                @can('update', $item)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('demarches.edit', $item)" wire:navigate />
                                @endcan
                                @can('delete', $item)
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="Supprimer cette démarche ?" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
