<?php

use App\Models\Signalement;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Signalements')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $mine = false;

    #[Url(except: '')]
    public string $filterNiveau = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Signalement::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMine(): void
    {
        $this->resetPage();
    }

    public function updatedFilterNiveau(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return Signalement::query()
            ->with('user')
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($q) => $q->where('titre', 'like', $term)->orWhere('description', 'like', $term)->orWhere('zone', 'like', $term));
            })
            ->when($this->mine, fn ($query) => $query->whereBelongsTo(auth()->user()))
            ->when($this->filterNiveau !== '', fn ($query) => $query->where('niveau', $this->filterNiveau))
            ->latest()
            ->paginate(10);
    }

    public function delete(int $id): void
    {
        $record = Signalement::findOrFail($id);
        $this->authorize('delete', $record);
        if ($record->photo) {
            Storage::disk('public')->delete($record->photo);
        }
        $record->delete();

        Flux::toast(variant: 'success', text: 'Signalement supprimé(e).');
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Signalements</flux:heading>
            <flux:text class="mt-1">{{ $this->items->total() }} élément(s)</flux:text>
        </div>

        @can('create', \App\Models\Signalement::class)
            <flux:button variant="primary" icon="plus" :href="route('signalements.create')" wire:navigate>
                Ajouter
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Rechercher…" class="sm:max-w-xs" />
        <flux:select wire:model.live="filterNiveau" class="sm:max-w-52">
            <flux:select.option value="">Niveau : tous</flux:select.option>
            @foreach (\App\Models\Signalement::NIVEAU_OPTIONS as $option)
                <flux:select.option :value="$option">{{ ucfirst($option) }}</flux:select.option>
            @endforeach
        </flux:select>
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
                <flux:table.column>Titre</flux:table.column>
                <flux:table.column>Niveau</flux:table.column>
                <flux:table.column>Zone</flux:table.column>
                <flux:table.column>Photo</flux:table.column>
                <flux:table.column>Auteur</flux:table.column>
                <flux:table.column>Créé le</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->items as $item)
                    <flux:table.row wire:key="row-{{ $item->id }}">
                        <flux:table.cell><flux:link :href="route('signalements.show', $item)" wire:navigate class="font-medium">{{ $item->titre }}</flux:link></flux:table.cell>
                        <flux:table.cell><flux:badge size="sm">{{ ucfirst($item->niveau ?? '—') }}</flux:badge></flux:table.cell>
                        <flux:table.cell>{{ $item->zone ?? '—' }}</flux:table.cell>
                        <flux:table.cell>@if ($item->photo)<img src="{{ Storage::url($item->photo) }}" alt="" class="size-10 rounded object-cover" />@endif</flux:table.cell>
                        <flux:table.cell>{{ $item->user?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $item->created_at->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye" :href="route('signalements.show', $item)" wire:navigate />
                                @can('update', $item)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('signalements.edit', $item)" wire:navigate />
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
