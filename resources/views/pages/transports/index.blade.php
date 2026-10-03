<?php

use App\Models\LigneTransport;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Transports')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $mode = '';

    #[Url(except: false)]
    public bool $perturbees = false;

    public function mount(): void
    {
        $this->authorize('viewAny', LigneTransport::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'mode', 'perturbees'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Recherche par numéro, nom de ligne ou nom d'arrêt, filtres mode et perturbations.
     *
     * @return Builder<LigneTransport>
     */
    protected function filteredQuery(): Builder
    {
        $mode = in_array($this->mode, LigneTransport::MODE_OPTIONS, true) ? $this->mode : '';
        $search = trim(mb_substr($this->search, 0, 100));

        return LigneTransport::query()
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.$search.'%';
                $query->where(fn ($q) => $q->where('numero', 'like', $term)->orWhere('nom', 'like', $term)->orWhere('arrets', 'like', $term));
            })
            ->when($mode !== '', fn ($query) => $query->where('mode', $mode))
            ->when($this->perturbees, fn ($query) => $query->where('etat', '!=', 'normal'));
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->orderByRaw("case when etat = 'normal' then 1 else 0 end")
            ->orderBy('numero')
            ->paginate(12);
    }

    /**
     * Toutes les perturbations en cours, affichées en tête de page quels que soient les filtres.
     *
     * @return Collection<int, LigneTransport>
     */
    #[Computed]
    public function perturbations(): Collection
    {
        return LigneTransport::query()
            ->where('etat', '!=', 'normal')
            ->orderByRaw("case when etat = 'interrompu' then 0 else 1 end")
            ->orderBy('numero')
            ->get();
    }

    /**
     * Arrêts de la ligne qui correspondent à la recherche (pour les mettre en évidence).
     *
     * @return array<int, string>
     */
    public function arretsTrouves(LigneTransport $ligne): array
    {
        $search = trim($this->search);

        if (mb_strlen($search) < 2) {
            return [];
        }

        return array_values(array_filter($ligne->listeArrets(), fn (string $arret) => mb_stripos($arret, $search) !== false));
    }

    public function resetFilters(): void
    {
        $this->authorize('viewAny', LigneTransport::class);

        $this->reset('search', 'mode', 'perturbees');
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $record = LigneTransport::findOrFail($id);
        $this->authorize('delete', $record);

        $record->delete();

        Flux::toast(variant: 'success', text: 'Ligne supprimée.');
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        label="Mobilité"
        title="Transports municipaux"
        :subtitle="$this->perturbations->isEmpty() ? 'Trafic normal sur tout le réseau' : $this->perturbations->count().' ligne(s) perturbée(s) en ce moment'"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Transports' => null]"
    >
        <x-slot:actions>
            @can('create', LigneTransport::class)
                <flux:button variant="primary" icon="plus" :href="route('transports.create')" wire:navigate>Ajouter une ligne</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    @if ($this->perturbations->isNotEmpty())
        <div role="alert" class="space-y-2 rounded-md border border-amber/40 bg-amber/8 p-4">
            <p class="flex items-center gap-2 font-semibold text-amber">
                <flux:icon name="exclamation-triangle" class="size-5" />
                Perturbations en cours
            </p>
            <ul class="space-y-2">
                @foreach ($this->perturbations as $ligne)
                    <li wire:key="perturbation-{{ $ligne->id }}" class="flex flex-col gap-1 text-sm sm:flex-row sm:items-start sm:gap-3">
                        <span class="flex shrink-0 items-center gap-2">
                            <x-tn.status-badge :etat="$ligne->etatBadge()">{{ $ligne->etatLabel() }}</x-tn.status-badge>
                            <a href="{{ route('transports.show', $ligne) }}" wire:navigate class="font-mono font-semibold text-ink hover:text-cyan">Ligne {{ $ligne->numero }}</a>
                        </span>
                        <span class="text-ink-2">{{ $ligne->perturbation ?? 'Perturbation signalée, informations à venir.' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Ligne ou arrêt (ex. 4, Marché, Université)…" aria-label="Rechercher une ligne ou un arrêt" class="sm:max-w-sm" />
        <flux:select wire:model.live="mode" aria-label="Mode de transport" class="sm:max-w-44">
            <flux:select.option value="">Tous les modes</flux:select.option>
            @foreach (LigneTransport::MODE_LABELS as $value => $label)
                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:checkbox wire:model.live="perturbees" label="Lignes perturbées uniquement" />
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">Mise à jour…</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="bus" title="Aucune ligne trouvée" text="Vérifiez l'orthographe de l'arrêt ou retirez un filtre.">
            @if ($search !== '' || $mode !== '' || $perturbees)
                <flux:button size="sm" wire:click="resetFilters">Réinitialiser la recherche</flux:button>
            @endif
        </x-tn.empty>
    @else
        <ul class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->items as $item)
                @php($arrets = $item->listeArrets())
                @php($trouves = $this->arretsTrouves($item))
                <li wire:key="row-{{ $item->id }}" @class([
                    'group relative flex min-w-0 flex-col rounded-md border bg-surface p-5 transition-colors hover:border-cyan/40',
                    'border-line' => ! $item->estPerturbee(),
                    'border-amber/50' => $item->etat === 'perturbe',
                    'border-magenta/50' => $item->etat === 'interrompu',
                ])>
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 min-w-10 shrink-0 items-center justify-center rounded-sm border border-cyan/25 bg-cyan/10 px-2 font-mono text-base font-bold text-cyan" aria-hidden="true">{{ $item->numero }}</span>
                        <div class="min-w-0 flex-1">
                            <h2 class="font-semibold text-ink">
                                <a href="{{ route('transports.show', $item) }}" wire:navigate class="after:absolute after:inset-0 group-hover:text-cyan">
                                    <span class="sr-only">Ligne {{ $item->numero }} : </span>{{ $item->nom }}
                                </a>
                            </h2>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <span class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-ink-2">{{ $item->modeLabel() }}</span>
                                <x-tn.status-badge :etat="$item->etatBadge()">{{ $item->etatLabel() }}</x-tn.status-badge>
                            </div>
                        </div>
                    </div>

                    @if ($item->estPerturbee() && $item->perturbation)
                        <p class="mt-3 flex items-start gap-1.5 text-sm {{ $item->etat === 'interrompu' ? 'text-magenta' : 'text-amber' }}">
                            <flux:icon :name="$item->etat === 'interrompu' ? 'x-circle' : 'exclamation-triangle'" variant="micro" class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            <span class="line-clamp-3">{{ $item->perturbation }}</span>
                        </p>
                    @endif

                    @if (count($arrets) > 0)
                        <p class="mt-3 text-sm text-ink-2">
                            <span class="text-ink">{{ $arrets[0] }}</span>
                            <span aria-hidden="true">→</span>
                            <span class="text-ink">{{ end($arrets) }}</span>
                            · {{ count($arrets) }} arrêts
                        </p>
                    @endif

                    @if ($trouves !== [])
                        <p class="mt-2 flex flex-wrap gap-1.5 text-xs">
                            <span class="text-ink-2">Dessert :</span>
                            @foreach ($trouves as $arret)
                                <span class="rounded-xs border border-cyan/35 bg-cyan/8 px-1.5 py-0.5 text-cyan">{{ $arret }}</span>
                            @endforeach
                        </p>
                    @endif

                    <dl class="mt-4 space-y-1.5 border-t border-line pt-3 text-sm">
                        <div class="flex min-w-0 gap-2"><dt class="sr-only">Horaires</dt><flux:icon name="clock" class="mt-0.5 size-4 shrink-0 text-ink-2" /><dd class="min-w-0 truncate font-mono text-xs leading-5 text-ink-2">{{ \Illuminate\Support\Str::before($item->horaires, "\n") }}</dd></div>
                        @if ($item->frequence)
                            <div class="flex min-w-0 gap-2"><dt class="sr-only">Fréquence</dt><flux:icon name="arrow-path" class="mt-0.5 size-4 shrink-0 text-ink-2" /><dd class="min-w-0 truncate font-mono text-xs leading-5 text-ink-2">{{ $item->frequence }}</dd></div>
                        @endif
                    </dl>

                    @canany(['update', 'delete'], $item)
                        <div class="relative z-10 mt-3 flex justify-end gap-1">
                            @can('update', $item)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('transports.edit', $item)" wire:navigate aria-label="Modifier la ligne {{ $item->numero }}" />
                            @endcan
                            @can('delete', $item)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="Supprimer cette ligne ?" aria-label="Supprimer la ligne {{ $item->numero }}" />
                            @endcan
                        </div>
                    @endcanany
                </li>
            @endforeach
        </ul>

        <div>{{ $this->items->links() }}</div>
    @endif
</section>
