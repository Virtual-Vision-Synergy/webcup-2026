<?php

use App\Models\Service;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::public'), Title('Services municipaux')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Service::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return Builder<Service>
     */
    protected function filteredQuery(): Builder
    {
        return Service::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($q) => $q->where('nom', 'like', $term)->orWhere('description', 'like', $term)->orWhere('adresse', 'like', $term));
            });
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->orderBy('nom')
            ->paginate(12);
    }

    public function delete(int $id): void
    {
        $record = Service::findOrFail($id);
        $this->authorize('delete', $record);

        $record->delete();

        Flux::toast(variant: 'success', text: 'Service municipal supprimé.');
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Services municipaux</flux:heading>
            <flux:text class="mt-1">L'annuaire des services de la Mairie de Nova Terra : missions, horaires et contacts.</flux:text>
        </div>

        @can('create', \App\Models\Service::class)
            <flux:button variant="primary" icon="plus" :href="route('services.create')" wire:navigate>
                Ajouter un service
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Rechercher un service…" class="sm:max-w-xs" />
        <flux:text class="text-sm">{{ $this->items->total() }} service(s)</flux:text>
        <div wire:loading wire:target="search"><flux:text class="text-sm">Recherche…</flux:text></div>
    </div>

    @if ($this->items->isEmpty())
        <flux:card class="py-12 text-center">
            <flux:heading>Aucun service trouvé</flux:heading>
            <flux:text class="mt-2">
                {{ $search !== '' ? 'Essayez un autre mot-clé.' : "L'annuaire n'a pas encore de service." }}
            </flux:text>
        </flux:card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->items as $item)
                <flux:card wire:key="service-{{ $item->id }}" class="flex flex-col gap-3">
                    <flux:heading size="lg">
                        <flux:link :href="route('services.show', $item)" wire:navigate variant="ghost">{{ $item->nom }}</flux:link>
                    </flux:heading>

                    <flux:text class="line-clamp-3">{{ $item->description }}</flux:text>

                    <div class="mt-auto space-y-1 text-sm">
                        @if ($item->telephone)
                            <div class="flex items-center gap-2"><flux:icon.phone class="size-4 shrink-0" /> {{ $item->telephone }}</div>
                        @endif
                        @if ($item->horaires)
                            <div class="flex items-center gap-2"><flux:icon.clock class="size-4 shrink-0" /> <span class="truncate">{{ Str::before($item->horaires, "\n") }}</span></div>
                        @endif
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <flux:button size="sm" :href="route('services.show', $item)" wire:navigate icon-trailing="arrow-right">Voir la fiche</flux:button>

                        <div class="flex gap-1">
                            @can('update', $item)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('services.edit', $item)" wire:navigate aria-label="Modifier" />
                            @endcan
                            @can('delete', $item)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="Supprimer ce service ?" aria-label="Supprimer" />
                            @endcan
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <flux:pagination :paginator="$this->items" />
    @endif
</section>
