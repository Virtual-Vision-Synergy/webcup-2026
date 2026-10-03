<?php

use App\Models\Projet;
use App\Models\Quartier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Projets de la ville (F67), page publique : décision assumée, les citoyens consultent sans compte.
 * Lecture seule ici ; la création et la mise à jour se font dans l'espace agent.
 */
new #[Layout('layouts::public'), Title('Projets de la ville')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $etat = '';

    #[Url(except: '')]
    public string $quartier = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Projet::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedEtat(): void
    {
        $this->resetPage();
    }

    public function updatedQuartier(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'etat', 'quartier');
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return trim($this->search) !== '' || $this->etat !== '' || $this->quartier !== '';
    }

    /**
     * @return Collection<int, Quartier>
     */
    #[Computed]
    public function quartiers(): Collection
    {
        return Quartier::query()->orderBy('nom')->get(['id', 'nom']);
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return Projet::query()
            ->with('quartier:id,nom')
            ->when(trim($this->search) !== '', function ($query) {
                $term = '%'.trim($this->search).'%';
                $query->where(fn ($q) => $q->where('titre', 'like', $term)->orWhere('description', 'like', $term));
            })
            // Une valeur inconnue (URL modifiée à la main) est ignorée.
            ->when(in_array($this->etat, Projet::ETAT_OPTIONS, true), fn ($query) => $query->where('etat', $this->etat))
            ->when(ctype_digit($this->quartier), fn ($query) => $query->where('quartier_id', (int) $this->quartier))
            ->orderByRaw("case etat when 'en_cours' then 0 when 'a_l_etude' then 1 else 2 end")
            ->orderByDesc('date_debut')
            ->paginate(9);
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 lg:px-8">
    <x-tn.page-header
        label="Participation"
        title="Projets de la ville"
        subtitle="Ce que Nova Terra construit et améliore dans vos quartiers : où, quand, pour combien et où en sont les travaux."
    >
        <x-slot:actions>
            @can('create', Projet::class)
                <flux:button variant="primary" icon="plus" :href="route('agent.projets.create')">Nouveau projet</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Rechercher un projet (ex. école)…" aria-label="Rechercher un projet" clearable class="sm:max-w-sm" />
        <flux:select wire:model.live="etat" aria-label="Filtrer par état" class="sm:max-w-48">
            <flux:select.option value="">Tous les états</flux:select.option>
            @foreach (Projet::ETAT_LABELS as $valeur => $label)
                <flux:select.option value="{{ $valeur }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="quartier" aria-label="Filtrer par quartier" class="sm:max-w-48">
            <flux:select.option value="">Tous les quartiers</flux:select.option>
            @foreach ($this->quartiers as $q)
                <flux:select.option value="{{ $q->id }}">{{ $q->nom }}</flux:select.option>
            @endforeach
        </flux:select>
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">Mise à jour…</span>
    </div>

    @if ($this->items->isEmpty() && $this->hasFilters())
        <x-tn.empty icon="magnifying-glass" title="Aucun projet ne correspond" text="Essayez un autre mot, un autre état ou un autre quartier.">
            <flux:button variant="primary" icon="x-mark" wire:click="resetFilters">Effacer les filtres</flux:button>
        </x-tn.empty>
    @elseif ($this->items->isEmpty())
        <x-tn.empty icon="building-office-2" title="Aucun projet publié pour le moment" text="Les projets de la ville seront présentés ici dès leur lancement." />
    @else
        <ul class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->items as $item)
                @php($avancement = $item->avancement())
                <li wire:key="projet-{{ $item->id }}" class="group relative flex min-w-0 flex-col rounded-md border border-line bg-surface p-5 transition-colors hover:border-cyan/40">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-tn.status-badge :etat="$item->badgeEtat()">{{ Projet::libelleEtat($item->etat) }}</x-tn.status-badge>
                        <flux:badge size="sm" icon="map-pin">{{ $item->quartier?->nom ?? 'Toute la ville' }}</flux:badge>
                    </div>

                    <h2 class="mt-3 font-semibold text-ink">
                        <a href="{{ route('projets.show', $item) }}" wire:navigate class="after:absolute after:inset-0 group-hover:text-cyan">{{ $item->titre }}</a>
                    </h2>
                    <p class="mt-1 line-clamp-2 text-sm text-ink-2">{{ $item->description }}</p>

                    <div class="mt-4">
                        <div class="flex justify-between text-xs text-ink-2">
                            <span>Avancement</span>
                            <span class="font-mono text-ink">{{ $avancement }} %</span>
                        </div>
                        <div class="mt-1 h-1.5 rounded-full bg-line" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $avancement }}" aria-label="Avancement de {{ $item->titre }}">
                            <div class="h-1.5 rounded-full bg-cyan" style="width: {{ $avancement }}%"></div>
                        </div>
                    </div>

                    <dl class="mt-4 space-y-1.5 border-t border-line pt-3 text-sm">
                        @if ($item->date_debut || $item->date_fin)
                            <div class="flex gap-2">
                                <dt class="sr-only">Dates</dt>
                                <flux:icon name="calendar-days" class="mt-0.5 size-4 shrink-0 text-ink-2" />
                                <dd class="text-ink-2">
                                    {{ $item->date_debut?->translatedFormat('M Y') ?? '?' }} → {{ $item->date_fin?->translatedFormat('M Y') ?? '?' }}
                                </dd>
                            </div>
                        @endif
                        @if ($budget = $item->budgetFormate())
                            <div class="flex gap-2">
                                <dt class="sr-only">Budget</dt>
                                <flux:icon name="banknotes" class="mt-0.5 size-4 shrink-0 text-ink-2" />
                                <dd class="font-mono text-xs leading-5 text-ink-2">{{ $budget }}</dd>
                            </div>
                        @endif
                    </dl>
                </li>
            @endforeach
        </ul>

        <div>{{ $this->items->links() }}</div>
    @endif
</section>
