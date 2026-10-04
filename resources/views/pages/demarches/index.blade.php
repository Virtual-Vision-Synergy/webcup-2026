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

    /**
     * F79 : tris proposés. Liste blanche : la valeur de l'URL n'est jamais passée telle quelle à orderBy.
     */
    public const TRI_OPTIONS = ['recents' => 'Plus récentes', 'anciens' => 'Plus anciennes', 'statut' => 'Par statut'];

    /** F79 : filtres conservés dans l'URL (?categorie=…&service=…&statut=…&tri=…), historique pour le retour arrière. */
    #[Url(as: 'categorie', except: '', history: true)]
    public string $filterCategorie = '';

    #[Url(as: 'service', except: '', history: true)]
    public string $filterServiceId = '';

    #[Url(as: 'statut', except: '', history: true)]
    public string $filterStatut = '';

    #[Url(except: 'recents', history: true)]
    public string $tri = 'recents';

    public function mount(): void
    {
        $this->authorize('viewAny', Demarche::class);
        $this->ignorerFiltresInvalides();
    }

    /**
     * F79 : une valeur inconnue venue de l'URL est ignorée (retour au défaut), sans erreur.
     */
    protected function ignorerFiltresInvalides(): void
    {
        if (! in_array($this->filterCategorie, Service::CATEGORIE_OPTIONS, true)) {
            $this->filterCategorie = '';
        }

        if (! ctype_digit($this->filterServiceId)) {
            $this->filterServiceId = '';
        }

        if (! in_array($this->filterStatut, Demarche::STATUT_OPTIONS, true)) {
            $this->filterStatut = '';
        }

        if (! array_key_exists($this->tri, self::TRI_OPTIONS)) {
            $this->tri = 'recents';
        }
    }

    /**
     * F79 : au moins un filtre choisi par l'utilisateur (pour le message « aucun résultat »).
     */
    #[Computed]
    public function filtresActifs(): bool
    {
        return $this->search !== '' || $this->filterCategorie !== '' || $this->filterServiceId !== '' || $this->filterStatut !== '' || $this->mine;
    }

    /**
     * F79 : retour à la liste par défaut.
     */
    public function reinitialiser(): void
    {
        $this->authorize('viewAny', Demarche::class);
        $this->reset('search', 'mine', 'filterCategorie', 'filterServiceId', 'filterStatut', 'tri');
        $this->resetPage();
    }

    public function updatedFilterCategorie(): void
    {
        $this->ignorerFiltresInvalides();
        $this->resetPage();
    }

    public function updatedTri(): void
    {
        $this->ignorerFiltresInvalides();
        $this->resetPage();
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
        $this->ignorerFiltresInvalides();
        $this->resetPage();
    }

    public function updatedFilterStatut(): void
    {
        $this->ignorerFiltresInvalides();
        $this->resetPage();
    }

    /**
     * Recherche et filtres, partagés par la liste (et la carte si l'entité a des coordonnées).
     *
     * @return Builder<Demarche>
     */
    protected function filteredQuery(): Builder
    {
        // F70 : un agent ne voit que les démarches de ses services, un habitant les siennes.
        return Demarche::query()
            ->visibleTo(auth()->user())
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($q) => $q->where('titre', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->when(! $this->voitToutesLesDemarches || $this->mine, fn ($query) => $query->whereBelongsTo(auth()->user()))
            ->when($this->filterCategorie !== '', fn ($query) => $query->whereHas('service', fn ($q) => $q->where('categorie', $this->filterCategorie)))
            ->when($this->filterServiceId !== '', fn ($query) => $query->where('service_id', $this->filterServiceId))
            ->when($this->filterStatut !== '', fn ($query) => $query->where('statut', $this->filterStatut));
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with(['user', 'service', 'derniereReponse'])
            ->tap(fn (Builder $query) => $this->appliquerTri($query))
            ->paginate(10)
            ->appends($this->parametresUrl());
    }

    /**
     * F79 : tri choisi parmi TRI_OPTIONS uniquement ; colonnes et ordre fixés dans le code.
     *
     * @param  Builder<Demarche>  $query
     */
    protected function appliquerTri(Builder $query): void
    {
        match ($this->tri) {
            'anciens' => $query->oldest()->oldest('id'),
            'statut' => $query
                ->orderByRaw(
                    'CASE statut '.str_repeat('WHEN ? THEN ? ', count(Demarche::STATUT_OPTIONS)).'ELSE ? END',
                    [...collect(Demarche::STATUT_OPTIONS)->flatMap(fn (string $statut, int $rang) => [$statut, $rang])->all(), count(Demarche::STATUT_OPTIONS)],
                )
                ->latest()->latest('id'),
            default => $query->latest()->latest('id'),
        };
    }

    /**
     * F79 : filtres actifs, recopiés dans les liens de pagination.
     *
     * @return array<string, string>
     */
    protected function parametresUrl(): array
    {
        return array_filter([
            'search' => $this->search,
            'mine' => $this->mine ? '1' : '',
            'categorie' => $this->filterCategorie,
            'service' => $this->filterServiceId,
            'statut' => $this->filterStatut,
            'tri' => $this->tri === 'recents' ? '' : $this->tri,
        ], fn (string $valeur) => $valeur !== '');
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

        Flux::toast(variant: 'success', text: __('Démarche supprimée.'));
    }
}; ?>


<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        :label="__('Démarches')"
        :title="__('Mes démarches')"
        :subtitle="__(($this->voitToutesLesDemarches ? ':n démarche(s) au total' : ':n démarche(s)'), ['n' => $this->items->total()])"
        :breadcrumb="[__('Mon espace') => route('dashboard'), __('Démarches') => null]"
    >
        <x-slot:actions>
            <flux:button icon="clock" :href="route('demarches.historique')" wire:navigate>Historique</flux:button>
            <flux:button icon="arrow-down-tray" :href="route('demarches.recapitulatif')">Télécharger le récapitulatif</flux:button>
            @can('create', Demarche::class)
                <flux:button variant="primary" icon="plus" :href="route('demarches.create')" class="tn-cta" wire:navigate>
                    {{ __('Nouvelle démarche') }}
                </flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <x-tn.aide id="demarches-index">Suivez ici l'avancement de vos démarches. Pour en lancer une, utilisez « Nouvelle démarche ».</x-tn.aide>

    {{-- Filtres --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Rechercher…') }}" aria-label="{{ __('Rechercher une démarche') }}" class="sm:max-w-xs" />
        <flux:select wire:model.live="filterCategorie" aria-label="{{ __('Filtrer par sujet') }}" class="sm:max-w-52">
            <flux:select.option value="">{{ __('Sujet : tous') }}</flux:select.option>
            @foreach (Service::CATEGORIE_OPTIONS as $option)
                <flux:select.option :value="$option">{{ Service::labelCategorie($option) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterServiceId" aria-label="{{ __('Filtrer par service') }}" class="sm:max-w-52">
            <flux:select.option value="">{{ __('Service : tous') }}</flux:select.option>
            @foreach ($this->serviceOptions as $option)
                <flux:select.option :value="$option->id">{{ $option->nom }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterStatut" aria-label="{{ __('Filtrer par statut') }}" class="sm:max-w-52">
            <flux:select.option value="">{{ __('Statut : tous') }}</flux:select.option>
            @foreach (Demarche::STATUT_OPTIONS as $option)
                <flux:select.option :value="$option">{{ Demarche::libelleStatut($option) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="tri" aria-label="{{ __('Trier les démarches') }}" class="sm:max-w-52">
            @foreach ($this::TRI_OPTIONS as $option => $libelle)
                <flux:select.option :value="$option">Tri : {{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>
        @if ($this->voitToutesLesDemarches)
            <flux:checkbox wire:model.live="mine" label="{{ __('Mes démarches uniquement') }}" />
        @endif
        @if ($this->filtresActifs || $tri !== 'recents')
            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="reinitialiser">Réinitialiser</flux:button>
        @endif
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">{{ __('Mise à jour…') }}</span>
    </div>

    @if ($this->items->isEmpty() && $this->filtresActifs)
        <x-tn.empty icon="funnel" title="Aucune demande ne correspond à ces filtres." text="Modifiez le sujet, le service, le statut ou la recherche, ou repartez de la liste complète.">
            <flux:button variant="primary" icon="x-mark" wire:click="reinitialiser">Réinitialiser</flux:button>
        </x-tn.empty>
    @elseif ($this->items->isEmpty())
        <x-tn.empty icon="file-text" title="{{ __('Aucune démarche pour le moment') }}" text="{{ __('Modifiez les filtres ou déposez votre première démarche.') }}">
            <flux:button variant="primary" icon="plus" :href="route('demarches.create')" wire:navigate>{{ __('Nouvelle démarche') }}</flux:button>
        </x-tn.empty>
    @else
        {{-- Mobile : liste --}}
        <ul class="md:hidden">
            @foreach ($this->items as $item)
                <li wire:key="m-{{ $item->id }}">
                    <x-tn.list-row icon="file-text" :href="route('demarches.show', $item)" :stack="true">
                        <span class="block truncate font-medium text-ink">{{ $item->titre }}</span>
                        <span class="block truncate text-sm text-ink-2">{{ $item->service?->nom ?? __('Service non précisé') }} · <span class="font-mono text-xs">{{ $item->created_at->format('d.m.Y') }}</span></span>
                        <x-slot:aside>
                            <span class="flex flex-wrap gap-1">
                                @if ($item->urgence_medicale)
                                    <x-badge-urgence-medicale />
                                @endif
                                <x-tn.status-badge :etat="$item->etatStatut()">{{ Demarche::libelleStatut($item->statut) }}</x-tn.status-badge>
                                @if ($item->reponseEnvoyee())
                                    <x-tn.status-badge etat="normal">{{ __('Réponse de la mairie') }}</x-tn.status-badge>
                                @endif
                            </span>
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
                    <flux:table.column>{{ __('Objet') }}</flux:table.column>
                    <flux:table.column>{{ __('Service') }}</flux:table.column>
                    <flux:table.column>{{ __('Statut') }}</flux:table.column>
                    @if ($this->voitToutesLesDemarches)
                        <flux:table.column>{{ __('Auteur') }}</flux:table.column>
                    @endif
                    <flux:table.column>{{ __('Déposée le') }}</flux:table.column>
                    <flux:table.column><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->items as $item)
                        <flux:table.row wire:key="row-{{ $item->id }}">
                            <flux:table.cell><a href="{{ route('demarches.show', $item) }}" wire:navigate class="font-medium text-ink hover:text-cyan">{{ $item->titre }}</a></flux:table.cell>
                            <flux:table.cell>{{ $item->service?->nom ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @if ($item->urgence_medicale)
                                        <x-badge-urgence-medicale />
                                    @endif
                                    <x-tn.status-badge :etat="$item->etatStatut()">{{ Demarche::libelleStatut($item->statut) }}</x-tn.status-badge>
                                    @if ($item->reponseEnvoyee())
                                        <x-tn.status-badge etat="normal">{{ __('Réponse de la mairie') }}</x-tn.status-badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            @if ($this->voitToutesLesDemarches)
                                <flux:table.cell>{{ $item->user?->name }}</flux:table.cell>
                            @endif
                            <flux:table.cell class="font-mono text-xs">{{ $item->created_at->format('d.m.Y') }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="eye" :href="route('demarches.show', $item)" wire:navigate aria-label="{{ __('Voir') }}" />
                                    @can('update', $item)
                                        <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('demarches.edit', $item)" wire:navigate aria-label="{{ __('Modifier') }}" />
                                    @endcan
                                    @can('delete', $item)
                                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="{{ __('Supprimer cette démarche ?') }}" aria-label="{{ __('Supprimer') }}" />
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
