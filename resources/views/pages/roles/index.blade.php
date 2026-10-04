<?php

use App\Models\Role;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Rôles')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Role::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Recherche et filtres, partagés par la liste (et la carte si l'entité a des coordonnées).
     *
     * @return Builder<Role>
     */
    protected function filteredQuery(): Builder
    {
        return Role::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('label', 'like', $term));
            });
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->withCount('users')
            ->orderBy('id')
            ->paginate(10);
    }

    public function delete(int $id): void
    {
        $record = Role::findOrFail($id);
        $this->authorize('delete', $record);

        $record->delete();

        Flux::toast(variant: 'success', text: __('Rôle supprimé(e).'));
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        label="{{ __('Administration') }}"
        title="{{ __('Rôles') }}"
        :subtitle="__(':n élément(s)', ['n' => $this->items->total()])"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Rôles' => null]"
    >
        <x-slot:actions>
            @can('create', \App\Models\Role::class)
                <flux:button variant="primary" icon="plus" :href="route('roles.create')" wire:navigate>
                    {{ __('Ajouter') }}
                </flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Rechercher…') }}" class="sm:max-w-xs" />
    </div>

    @if ($this->items->isEmpty())
        <flux:card class="py-12 text-center">
            <flux:heading>{{ __('Aucun élément pour le moment') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Modifie les filtres ou ajoute un premier élément.') }}</flux:text>
        </flux:card>
    @else
        <flux:table :paginate="$this->items">
            <flux:table.columns>
                <flux:table.column>{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Libellé') }}</flux:table.column>
                <flux:table.column>{{ __('Comptes') }}</flux:table.column>
                <flux:table.column>{{ __('Créé le') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->items as $item)
                    <flux:table.row wire:key="row-{{ $item->id }}">
                        <flux:table.cell><flux:link :href="route('roles.show', $item)" wire:navigate class="font-medium">{{ $item->code }}</flux:link></flux:table.cell>
                        <flux:table.cell>{{ $item->label ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $item->users_count }}</flux:table.cell>
                        <flux:table.cell>{{ $item->created_at->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye" :href="route('roles.show', $item)" wire:navigate />
                                @can('update', $item)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('roles.edit', $item)" wire:navigate />
                                @endcan
                                @can('delete', $item)
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="{{ __('Supprimer cet élément ?') }}" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
