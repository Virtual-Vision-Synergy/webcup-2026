<?php

use App\Models\Actualite;
use App\Services\OptimiseurImage;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Actualites')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $mine = false;


    public function mount(): void
    {
        $this->authorize('viewAny', Actualite::class);
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
     * @return Builder<Actualite>
     */
    protected function filteredQuery(): Builder
    {
        return Actualite::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($q) => $q->where('titre', 'like', $term)->orWhere('contenu', 'like', $term));
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
        $record = Actualite::findOrFail($id);
        $this->authorize('delete', $record);
        if ($record->image) {
            app(OptimiseurImage::class)->supprimer($record->image);
        }
        $record->delete();

        Flux::toast(variant: 'success', text: 'Actualite supprimé(e).');
    }
}; ?>

@php
    use Illuminate\Support\Str;

    // L'article le plus récent passe « à la une » sur la première page, hors recherche.
    $aLaUne = $this->items->onFirstPage() && $this->search === '' ? $this->items->first() : null;
    $suivants = $aLaUne ? $this->items->slice(1) : $this->items;
@endphp

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        label="Fil du Haut Conseil"
        title="Actualités"
        :subtitle="$this->items->total().' annonce(s) publiée(s)'"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Actualités' => null]"
    >
        <x-slot:actions>
            @can('create', Actualite::class)
                <flux:button variant="primary" icon="plus" :href="route('actualites.create')" wire:navigate>Publier</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Rechercher une annonce…" aria-label="Rechercher une annonce" class="sm:max-w-sm" />
        @can('create', Actualite::class)
            <flux:checkbox wire:model.live="mine" label="Mes publications uniquement" />
        @endcan
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">Mise à jour…</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="newspaper" title="Aucune annonce pour le moment" text="Les publications du Haut Conseil apparaîtront ici. Modifiez la recherche pour élargir les résultats." />
    @else
        @if ($aLaUne)
            {{-- À LA UNE --}}
            <article class="grid overflow-hidden rounded-md border border-line bg-surface lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
                <div class="tn-sky relative min-h-56 overflow-hidden lg:min-h-80">
                    @if ($aLaUne->image)
                        <x-tn.image :chemin="$aLaUne->image" :prioritaire="true" sizes="(min-width: 1024px) 55vw, 100vw" class="absolute inset-0 size-full object-cover" />
                    @else
                        <div class="tn-planet -right-16 -bottom-24 size-[280px]" aria-hidden="true"></div>
                        <div class="tn-grid" aria-hidden="true"></div>
                    @endif
                    <x-tn.status-badge etat="info" :live="true" class="absolute top-4 left-4 bg-night/60! backdrop-blur">À la une</x-tn.status-badge>
                </div>
                <div class="flex flex-col p-6 md:p-8">
                    <time datetime="{{ $aLaUne->date?->toDateString() }}" class="font-mono text-sm text-ink-2">{{ $aLaUne->date?->translatedFormat('l j F Y') }}</time>
                    <h2 class="tn-display mt-3 text-2xl leading-tight font-semibold text-ink md:text-[1.875rem]">
                        <a href="{{ route('actualites.show', $aLaUne) }}" wire:navigate class="hover:text-cyan">{{ $aLaUne->titre }}</a>
                    </h2>
                    <p class="mt-3 leading-relaxed text-ink-2">{{ Str::limit(strip_tags((string) $aLaUne->contenu), 260) }}</p>
                    <a href="{{ route('actualites.show', $aLaUne) }}" wire:navigate class="tn-btn-secondary mt-auto w-fit max-lg:mt-6">Lire l’annonce</a>
                </div>
            </article>
        @endif

        {{-- FIL CHRONOLOGIQUE --}}
        @if ($suivants->isNotEmpty())
            <ol>
                @foreach ($suivants as $item)
                    <li wire:key="row-{{ $item->id }}" class="grid gap-2 border-t border-line py-5 sm:grid-cols-[160px_minmax(0,1fr)_auto] sm:gap-6">
                        <time datetime="{{ $item->date?->toDateString() }}" class="font-mono text-sm text-ink-2">{{ $item->date?->format('d.m.Y') ?? '—' }}</time>
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold text-ink">
                                <a href="{{ route('actualites.show', $item) }}" wire:navigate class="hover:text-cyan">{{ $item->titre }}</a>
                            </h3>
                            <p class="mt-1 line-clamp-2 text-ink-2">{{ Str::limit(strip_tags((string) $item->contenu), 200) }}</p>
                        </div>
                        <div class="flex items-start gap-1">
                            @can('update', $item)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('actualites.edit', $item)" wire:navigate aria-label="Modifier" />
                            @endcan
                            @can('delete', $item)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="Supprimer cette annonce ?" aria-label="Supprimer" />
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif

        <div>{{ $this->items->links() }}</div>
    @endif
</section>
