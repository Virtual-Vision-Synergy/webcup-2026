<?php

use App\Models\Service;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Services')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $mine = false;


    public function mount(): void
    {
        $this->authorize('viewAny', Service::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMine(): void
    {
        $this->resetPage();
    }

    /**
     * Recherche et filtres, partagés par la liste (et la carte si l'entité a des coordonnées).
     *
     * @return Builder<Service>
     */
    protected function filteredQuery(): Builder
    {
        return Service::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($q) => $q->where('nom', 'like', $term)->orWhere('description', 'like', $term)->orWhere('icone', 'like', $term));
            })
            ->when($this->mine, fn ($query) => $query->whereBelongsTo(auth()->user()));
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with('user')
            ->latest()
            ->paginate(10);
    }

    public function delete(int $id): void
    {
        $record = Service::findOrFail($id);
        $this->authorize('delete', $record);

        $record->delete();

        Flux::toast(variant: 'success', text: 'Service supprimé(e).');
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Services</flux:heading>
            <flux:text class="mt-1">{{ $this->items->total() }} élément(s)</flux:text>
        </div>

        @can('create', \App\Models\Service::class)
            <flux:button variant="primary" icon="plus" :href="route('services.create')" wire:navigate>
                Ajouter
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Rechercher…" class="sm:max-w-xs" />

        <flux:checkbox wire:model.live="mine" label="Mes éléments uniquement" />
    </div>

    @if ($this->items->isEmpty())
        <flux:card class="py-12 text-center">
            <flux:heading>Aucun élément pour le moment</flux:heading>
            <flux:text class="mt-2">Modifie les filtres ou ajoute un premier élément.</flux:text>
        </flux:card>
    @else
        <flux:table :paginate="$this->items">
            <flux:table.columns>
                <flux:table.column>Nom</flux:table.column>
                <flux:table.column>Icone</flux:table.column>
                <flux:table.column>Auteur</flux:table.column>
                <flux:table.column>Créé le</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->items as $item)
                    <flux:table.row wire:key="row-{{ $item->id }}">
                        <flux:table.cell><flux:link :href="route('services.show', $item)" wire:navigate class="font-medium">{{ $item->nom }}</flux:link></flux:table.cell>
                        <flux:table.cell>{{ $item->icone ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $item->user?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $item->created_at->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye" :href="route('services.show', $item)" wire:navigate />
                                @can('update', $item)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('services.edit', $item)" wire:navigate />
                                @endcan
                                @can('delete', $item)
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="Supprimer cet élément ?" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
