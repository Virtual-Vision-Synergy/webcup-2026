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

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        label="Annuaire"
        title="Services municipaux"
        :subtitle="$this->items->total().' service(s) référencé(s)'"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Services' => null]"
    >
        <x-slot:actions>
            @can('create', Service::class)
                <flux:button variant="primary" icon="plus" :href="route('services.create')" wire:navigate>Ajouter</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Rechercher un service…" aria-label="Rechercher un service" class="sm:max-w-sm" />
        @can('create', Service::class)
            <flux:checkbox wire:model.live="mine" label="Mes services uniquement" />
        @endcan
        <span wire:loading class="font-mono text-[11px] uppercase tracking-[.06em] text-cyan">Mise à jour…</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="landmark" title="Aucun service pour le moment" text="Modifiez la recherche ou revenez plus tard : l'annuaire est en cours de publication." />
    @else
        <ul class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->items as $item)
                <li wire:key="row-{{ $item->id }}" class="group relative flex min-w-0 flex-col rounded-md border border-line bg-surface p-5 transition-colors hover:border-cyan/40">
                    <div class="flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-sm border border-cyan/18 bg-cyan/8 text-cyan" aria-hidden="true">
                            <flux:icon name="landmark" class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="font-semibold text-ink">
                                <a href="{{ route('services.show', $item) }}" wire:navigate class="after:absolute after:inset-0 group-hover:text-cyan">{{ $item->nom }}</a>
                            </h2>
                            @if ($item->description)
                                <p class="mt-1 line-clamp-2 text-sm text-ink-2">{{ $item->description }}</p>
                            @endif
                        </div>
                    </div>
                    <dl class="mt-4 space-y-1.5 border-t border-line pt-3 text-sm">
                        @if ($item->horaires)
                            <div class="flex min-w-0 gap-2"><dt class="sr-only">Horaires</dt><flux:icon name="clock" class="mt-0.5 size-4 shrink-0 text-ink-2" /><dd class="min-w-0 truncate font-mono text-xs leading-5 text-ink-2">{{ \Illuminate\Support\Str::before($item->horaires, "\n") }}</dd></div>
                        @endif
                        @if ($item->telephone)
                            <div class="flex gap-2"><dt class="sr-only">Téléphone</dt><flux:icon name="phone" class="mt-0.5 size-4 shrink-0 text-ink-2" /><dd class="font-mono text-xs leading-5 text-ink-2">{{ $item->telephone }}</dd></div>
                        @endif
                    </dl>
                    @canany(['update', 'delete'], $item)
                        <div class="relative z-10 mt-3 flex justify-end gap-1">
                            @can('update', $item)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('services.edit', $item)" wire:navigate aria-label="Modifier {{ $item->nom }}" />
                            @endcan
                            @can('delete', $item)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="Supprimer ce service ?" aria-label="Supprimer {{ $item->nom }}" />
                            @endcan
                        </div>
                    @endcanany
                </li>
            @endforeach
        </ul>

        <div>{{ $this->items->links() }}</div>
    @endif
</section>
