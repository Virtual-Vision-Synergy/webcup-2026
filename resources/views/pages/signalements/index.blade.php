<?php

use App\Models\Signalement;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
    public string $filterCategorie = '';

    #[Url(except: '')]
    public string $filterStatut = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Signalement::class);
    }

    /**
     * Agents et admins voient tous les signalements ; un citoyen ne voit que les siens.
     */
    #[Computed]
    public function voitTousLesSignalements(): bool
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

    public function updatedFilterCategorie(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatut(): void
    {
        $this->resetPage();
    }

    /**
     * @return Builder<Signalement>
     */
    protected function filteredQuery(): Builder
    {
        return Signalement::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($q) => $q->where('description', 'like', $term)->orWhere('lieu', 'like', $term));
            })
            ->when(! $this->voitTousLesSignalements || $this->mine, fn ($query) => $query->whereBelongsTo(auth()->user()))
            ->when($this->filterCategorie !== '', fn ($query) => $query->where('categorie', $this->filterCategorie))
            ->when($this->filterStatut !== '', fn ($query) => $query->where('statut', $this->filterStatut));
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
        $record = Signalement::findOrFail($id);
        $this->authorize('delete', $record);

        if ($record->photo) {
            Storage::disk('public')->delete($record->photo);
        }

        $record->delete();

        Flux::toast(variant: 'success', text: __('Signalement supprimé.'));
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        label="{{ __('Signalements') }}"
        :title="$this->voitTousLesSignalements ? __('Signalements') : __('Mes signalements')"
        :subtitle="__(($this->voitTousLesSignalements ? ':n signalement(s) au total' : ':n signalement(s)'), ['n' => $this->items->total()])"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Signalements' => null]"
    >
        <x-slot:actions>
            @can('create', Signalement::class)
                <flux:button variant="primary" icon="plus" :href="route('signalements.create')" class="tn-cta" wire:navigate>
                    {{ __('Signaler un problème') }}
                </flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    {{-- Filtres --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Rechercher (lieu, description)…') }}" aria-label="{{ __('Rechercher un signalement') }}" class="sm:max-w-xs" />
        <flux:select wire:model.live="filterCategorie" aria-label="{{ __('Filtrer par catégorie') }}" class="sm:max-w-56">
            <flux:select.option value="">{{ __('Catégorie : toutes') }}</flux:select.option>
            @foreach (Signalement::CATEGORIE_OPTIONS as $option)
                <flux:select.option :value="$option">{{ __(Signalement::libelleCategorie($option)) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterStatut" aria-label="{{ __('Filtrer par état') }}" class="sm:max-w-52">
            <flux:select.option value="">{{ __('État : tous') }}</flux:select.option>
            @foreach (Signalement::STATUT_OPTIONS as $option)
                <flux:select.option :value="$option">{{ Signalement::libelleStatut($option) }}</flux:select.option>
            @endforeach
        </flux:select>
        @if ($this->voitTousLesSignalements)
            <flux:checkbox wire:model.live="mine" label="{{ __('Mes signalements uniquement') }}" />
        @endif
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">{{ __('Mise à jour…') }}</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="exclamation-triangle" title="{{ __('Aucun signalement pour le moment') }}" text="{{ __('Un lampadaire cassé, un nid-de-poule, un dépôt sauvage ? Signalez-le à la mairie.') }}">
            <flux:button variant="primary" icon="plus" :href="route('signalements.create')" wire:navigate>{{ __('Signaler un problème') }}</flux:button>
        </x-tn.empty>
    @else
        {{-- Mobile : liste --}}
        <ul class="md:hidden">
            @foreach ($this->items as $item)
                <li wire:key="m-{{ $item->id }}">
                    <x-tn.list-row icon="exclamation-triangle" :href="route('signalements.show', $item)" :stack="true">
                        <span class="block truncate font-medium text-ink">{{ __(Signalement::libelleCategorie($item->categorie)) }}</span>
                        <span class="block truncate text-sm text-ink-2">{{ $item->lieu }} · <span class="font-mono text-xs">{{ $item->created_at->format('d.m.Y') }}</span></span>
                        <x-slot:aside>
                            <x-tn.status-badge :etat="$item->etatStatut()">{{ Signalement::libelleStatut($item->statut) }}</x-tn.status-badge>
                        </x-slot:aside>
                    </x-tn.list-row>
                </li>
            @endforeach
        </ul>
        <div class="md:hidden">{{ $this->items->links() }}</div>

        {{-- Desktop : tableau --}}
        <x-tn.surface padding="px-4 py-2" class="max-md:hidden">
            <flux:table :paginate="$this->items">
                <flux:table.columns>
                    <flux:table.column>{{ __('Catégorie') }}</flux:table.column>
                    <flux:table.column>{{ __('Lieu') }}</flux:table.column>
                    <flux:table.column>{{ __('État') }}</flux:table.column>
                    @if ($this->voitTousLesSignalements)
                        <flux:table.column>{{ __('Signalé par') }}</flux:table.column>
                    @endif
                    <flux:table.column>{{ __('Signalé le') }}</flux:table.column>
                    <flux:table.column><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->items as $item)
                        <flux:table.row wire:key="row-{{ $item->id }}">
                            <flux:table.cell><a href="{{ route('signalements.show', $item) }}" wire:navigate class="font-medium text-ink hover:text-cyan">{{ __(Signalement::libelleCategorie($item->categorie)) }}</a></flux:table.cell>
                            <flux:table.cell class="max-w-72 truncate">{{ $item->lieu }}</flux:table.cell>
                            <flux:table.cell><x-tn.status-badge :etat="$item->etatStatut()">{{ Signalement::libelleStatut($item->statut) }}</x-tn.status-badge></flux:table.cell>
                            @if ($this->voitTousLesSignalements)
                                <flux:table.cell>{{ $item->user?->name }}</flux:table.cell>
                            @endif
                            <flux:table.cell class="font-mono text-xs">{{ $item->created_at->format('d.m.Y') }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="eye" :href="route('signalements.show', $item)" wire:navigate aria-label="{{ __('Voir') }}" />
                                    @can('update', $item)
                                        <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('signalements.edit', $item)" wire:navigate aria-label="{{ __('Modifier') }}" />
                                    @endcan
                                    @can('delete', $item)
                                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="{{ __('Supprimer ce signalement ?') }}" aria-label="{{ __('Supprimer') }}" />
                                    @endcan
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-tn.surface>
    @endif
</section>
