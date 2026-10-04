<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\Signalement;
use App\Services\OptimiseurImage;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Signalements')] class extends Component {
    use ThrottlesPerUser, WithPagination;

    /**
     * Tris proposés (F52, F79). Liste blanche : la valeur de l'URL n'est jamais passée telle quelle à orderBy.
     */
    public const TRI_OPTIONS = [
        'recents' => 'Plus récents',
        'anciens' => 'Plus anciens',
        'statut' => 'Par état',
        'soutiens' => 'Plus soutenus',
    ];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $mine = false;

    /** F79 : filtres conservés dans l'URL (?categorie=…&statut=…&tri=…), historique pour le retour arrière. */
    #[Url(as: 'categorie', except: '', history: true)]
    public string $filterCategorie = '';

    #[Url(as: 'statut', except: '', history: true)]
    public string $filterStatut = '';

    /** Vue citoyen : '' = mes signalements, 'publiques' = demandes ouvertes des autres habitants (F52). */
    #[Url(except: '')]
    public string $vue = '';

    #[Url(except: 'recents', history: true)]
    public string $tri = 'recents';

    public function mount(): void
    {
        $this->authorize('viewAny', Signalement::class);
        $this->ignorerFiltresInvalides();
    }

    /**
     * F79 : une valeur inconnue venue de l'URL est ignorée (retour au défaut), sans erreur.
     */
    protected function ignorerFiltresInvalides(): void
    {
        if (! in_array($this->filterCategorie, Signalement::CATEGORIE_OPTIONS, true)) {
            $this->filterCategorie = '';
        }

        if (! in_array($this->filterStatut, Signalement::STATUT_OPTIONS, true)) {
            $this->filterStatut = '';
        }

        if (! array_key_exists($this->tri, self::TRI_OPTIONS)) {
            $this->tri = 'recents';
        }

        if (! in_array($this->vue, ['', 'publiques'], true)) {
            $this->vue = '';
        }
    }

    /**
     * F79 : au moins un filtre ou tri choisi par l'utilisateur (pour le message « aucun résultat »).
     */
    #[Computed]
    public function filtresActifs(): bool
    {
        return $this->search !== '' || $this->filterCategorie !== '' || $this->filterStatut !== '' || $this->mine;
    }

    /**
     * F79 : retour à la liste par défaut (la vue citoyen choisie est conservée).
     */
    public function reinitialiser(): void
    {
        $this->authorize('viewAny', Signalement::class);
        $this->reset('search', 'mine', 'filterCategorie', 'filterStatut', 'tri');
        $this->resetPage();
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

    /**
     * Un citoyen consulte les demandes ouvertes de tous les habitants pour les soutenir (F52).
     * Les données affichées y sont limitées : ni auteur, ni photo.
     */
    #[Computed]
    public function demandesPubliques(): bool
    {
        return ! $this->voitTousLesSignalements && $this->vue === 'publiques';
    }

    public function updatedVue(): void
    {
        $this->ignorerFiltresInvalides();
        $this->reset('filterStatut');
        $this->resetPage();
    }

    public function updatedTri(): void
    {
        $this->ignorerFiltresInvalides();
        $this->resetPage();
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
        $this->ignorerFiltresInvalides();
        $this->resetPage();
    }

    public function updatedFilterStatut(): void
    {
        $this->ignorerFiltresInvalides();
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
            ->when($this->demandesPubliques, fn ($query) => $query->whereIn('statut', Signalement::STATUTS_OUVERTS))
            ->when(! $this->demandesPubliques && (! $this->voitTousLesSignalements || $this->mine), fn ($query) => $query->whereBelongsTo(auth()->user()))
            ->when($this->filterCategorie !== '', fn ($query) => $query->where('categorie', $this->filterCategorie))
            ->when($this->filterStatut !== '', fn ($query) => $query->where('statut', $this->filterStatut));
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with('user')
            ->withCount('soutiens')
            ->withExists(['soutiens as soutenu_par_moi' => fn ($query) => $query->where('user_id', auth()->id())])
            ->tap(fn (Builder $query) => $this->appliquerTri($query))
            ->paginate(10)
            ->appends($this->parametresUrl());
    }

    /**
     * F79 : tri choisi parmi TRI_OPTIONS uniquement ; colonnes et ordre fixés dans le code.
     *
     * @param  Builder<Signalement>  $query
     */
    protected function appliquerTri(Builder $query): void
    {
        match ($this->tri) {
            'anciens' => $query->oldest()->oldest('id'),
            'statut' => $query
                ->orderByRaw(
                    'CASE statut '.str_repeat('WHEN ? THEN ? ', count(Signalement::STATUT_OPTIONS)).'ELSE ? END',
                    [...collect(Signalement::STATUT_OPTIONS)->flatMap(fn (string $statut, int $rang) => [$statut, $rang])->all(), count(Signalement::STATUT_OPTIONS)],
                )
                ->latest()->latest('id'),
            'soutiens' => $query->orderByDesc('soutiens_count')->latest()->latest('id'),
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
            'statut' => $this->filterStatut,
            'vue' => $this->vue,
            'tri' => $this->tri === 'recents' ? '' : $this->tri,
        ], fn (string $valeur) => $valeur !== '');
    }

    public function soutenir(int $id): void
    {
        $record = Signalement::findOrFail($id);
        $this->authorize('soutenir', $record);
        $this->throttlePerUser('soutien', maxAttempts: 30, decaySeconds: 60);

        $record->ajouterSoutien(auth()->user());
        unset($this->items);

        Flux::toast(variant: 'success', text: 'Votre soutien a bien été pris en compte.');
    }

    public function retirerSoutien(int $id): void
    {
        $record = Signalement::findOrFail($id);
        $this->authorize('retirerSoutien', $record);
        $this->throttlePerUser('soutien', maxAttempts: 30, decaySeconds: 60);

        $record->retirerSoutien(auth()->user());
        unset($this->items);

        Flux::toast(text: 'Votre soutien a été retiré.');
    }

    public function delete(int $id): void
    {
        $record = Signalement::findOrFail($id);
        $this->authorize('delete', $record);

        if ($record->photo) {
            app(OptimiseurImage::class)->supprimer($record->photo);
        }

        $record->delete();

        Flux::toast(variant: 'success', text: 'Signalement supprimé.');
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        label="Signalements"
        :title="$this->voitTousLesSignalements ? 'Signalements' : ($this->demandesPubliques ? 'Demandes des habitants' : 'Mes signalements')"
        :subtitle="$this->items->total().($this->demandesPubliques ? ' demande(s) en cours, à soutenir' : ' signalement(s)'.($this->voitTousLesSignalements ? ' au total' : ''))"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Signalements' => null]"
    >
        <x-slot:actions>
            @can('create', Signalement::class)
                <flux:button variant="primary" icon="plus" :href="route('signalements.create')" class="tn-cta" wire:navigate>
                    Signaler un problème
                </flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    {{-- Vue citoyen : mes signalements / demandes des autres habitants (F52) --}}
    @unless ($this->voitTousLesSignalements)
        <div class="flex flex-wrap gap-2" role="group" aria-label="Choisir les signalements affichés">
            <flux:button size="sm" icon="user" wire:click="$set('vue', '')" :variant="$this->demandesPubliques ? 'outline' : 'primary'" :aria-pressed="$this->demandesPubliques ? 'false' : 'true'">
                Mes signalements
            </flux:button>
            <flux:button size="sm" icon="users" wire:click="$set('vue', 'publiques')" :variant="$this->demandesPubliques ? 'primary' : 'outline'" :aria-pressed="$this->demandesPubliques ? 'true' : 'false'">
                Soutenir une demande
            </flux:button>
        </div>
    @endunless

    {{-- Filtres --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Rechercher (lieu, description)…" aria-label="Rechercher un signalement" class="sm:max-w-xs" />
        <flux:select wire:model.live="filterCategorie" aria-label="Filtrer par catégorie" class="sm:max-w-56">
            <flux:select.option value="">Catégorie : toutes</flux:select.option>
            @foreach (Signalement::CATEGORIE_OPTIONS as $option)
                <flux:select.option :value="$option">{{ Signalement::libelleCategorie($option) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterStatut" aria-label="Filtrer par état" class="sm:max-w-52">
            <flux:select.option value="">État : tous</flux:select.option>
            @foreach ($this->demandesPubliques ? Signalement::STATUTS_OUVERTS : Signalement::STATUT_OPTIONS as $option)
                <flux:select.option :value="$option">{{ Signalement::libelleStatut($option) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="tri" aria-label="Trier les signalements" class="sm:max-w-52">
            @foreach ($this::TRI_OPTIONS as $option => $libelle)
                <flux:select.option :value="$option">Tri : {{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>
        @if ($this->voitTousLesSignalements)
            <flux:checkbox wire:model.live="mine" label="Mes signalements uniquement" />
        @endif
        @if ($this->filtresActifs || $tri !== 'recents')
            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="reinitialiser">Réinitialiser</flux:button>
        @endif
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">Mise à jour…</span>
    </div>

    @error('throttle')
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" role="alert" />
    @enderror

    @if ($this->items->isEmpty() && $this->filtresActifs)
        <x-tn.empty icon="funnel" title="{{ $this->demandesPubliques ? 'Aucune demande ne correspond à ces filtres.' : 'Aucun signalement ne correspond à ces filtres.' }}" text="Modifiez la catégorie, l'état ou la recherche, ou repartez de la liste complète.">
            <flux:button variant="primary" icon="x-mark" wire:click="reinitialiser">Réinitialiser</flux:button>
        </x-tn.empty>
    @elseif ($this->items->isEmpty() && $this->demandesPubliques)
        <x-tn.empty icon="users" title="Aucune demande à soutenir pour le moment" text="Quand un habitant signalera un problème, vous pourrez appuyer sa demande ici." />
    @elseif ($this->demandesPubliques)
        {{-- Demandes ouvertes des habitants : ni auteur ni photo, seulement de quoi décider de soutenir (F52) --}}
        <ul class="space-y-3">
            @foreach ($this->items as $item)
                @php($estAMoi = $item->user_id === auth()->id())
                <li wire:key="pub-{{ $item->id }}">
                    <x-tn.surface>
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-tn.status-badge :etat="$item->etatStatut()">{{ Signalement::libelleStatut($item->statut) }}</x-tn.status-badge>
                                    @if ($estAMoi)
                                        <x-tn.status-badge etat="info">Votre demande</x-tn.status-badge>
                                    @endif
                                </div>
                                <h3 class="font-medium text-ink">{{ Signalement::libelleCategorie($item->categorie) }} · {{ $item->lieu }}</h3>
                                <p class="text-sm text-ink-2">{{ Str::limit($item->description, 200) }}</p>
                                <p class="font-mono text-xs text-ink-2">Déposée le {{ $item->created_at->format('d.m.Y') }}</p>
                            </div>

                            <div class="flex flex-col gap-2 md:shrink-0 md:items-end">
                                <span class="inline-flex items-center gap-1 text-sm text-ink" aria-live="polite">
                                    <flux:icon.users variant="micro" />
                                    <span><strong>{{ $item->soutiens_count }}</strong> {{ $item->soutiens_count > 1 ? 'soutiens' : 'soutien' }}</span>
                                </span>
                                @if ($item->soutenu_par_moi)
                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-cyan/40 bg-cyan/10 px-3 py-1.5 text-sm font-medium text-cyan">
                                        <flux:icon.check-circle variant="mini" />
                                        Vous soutenez cette demande
                                    </span>
                                    <flux:button size="xs" variant="ghost" wire:click="retirerSoutien({{ $item->id }})" wire:confirm="Retirer votre soutien à cette demande ?" wire:loading.attr="disabled">
                                        Retirer mon soutien
                                    </flux:button>
                                @elseif ($estAMoi)
                                    <flux:button size="sm" variant="outline" icon="eye" :href="route('signalements.show', $item)" wire:navigate>Voir ma demande</flux:button>
                                @else
                                    @can('soutenir', $item)
                                        <flux:button size="sm" variant="outline" icon="hand-thumb-up" wire:click="soutenir({{ $item->id }})" wire:loading.attr="disabled">Je soutiens</flux:button>
                                    @endcan
                                @endif
                            </div>
                        </div>
                    </x-tn.surface>
                </li>
            @endforeach
        </ul>

        {{ $this->items->links() }}
    @elseif ($this->items->isEmpty())
        <x-tn.empty icon="exclamation-triangle" title="Aucun signalement pour le moment" text="Un lampadaire cassé, un nid-de-poule, un dépôt sauvage ? Signalez-le à la mairie.">
            <flux:button variant="primary" icon="plus" :href="route('signalements.create')" wire:navigate>Signaler un problème</flux:button>
        </x-tn.empty>
    @else
        {{-- Mobile : liste --}}
        <ul class="md:hidden">
            @foreach ($this->items as $item)
                <li wire:key="m-{{ $item->id }}">
                    <x-tn.list-row icon="exclamation-triangle" :href="route('signalements.show', $item)" :stack="true">
                        <span class="block truncate font-medium text-ink">{{ Signalement::libelleCategorie($item->categorie) }}</span>
                        <span class="block truncate text-sm text-ink-2">{{ $item->lieu }} · <span class="font-mono text-xs">{{ $item->created_at->format('d.m.Y') }}</span></span>
                        <x-slot:aside>
                            <span class="me-2 font-mono text-xs text-ink-2" title="Soutiens d'habitants">{{ $item->soutiens_count }} <flux:icon.users variant="micro" class="inline" /></span>
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
                    <flux:table.column>Catégorie</flux:table.column>
                    <flux:table.column>Lieu</flux:table.column>
                    <flux:table.column>État</flux:table.column>
                    <flux:table.column>Soutiens</flux:table.column>
                    @if ($this->voitTousLesSignalements)
                        <flux:table.column>Signalé par</flux:table.column>
                    @endif
                    <flux:table.column>Signalé le</flux:table.column>
                    <flux:table.column><span class="sr-only">Actions</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->items as $item)
                        <flux:table.row wire:key="row-{{ $item->id }}">
                            <flux:table.cell><a href="{{ route('signalements.show', $item) }}" wire:navigate class="font-medium text-ink hover:text-cyan">{{ Signalement::libelleCategorie($item->categorie) }}</a></flux:table.cell>
                            <flux:table.cell class="max-w-72 truncate">{{ $item->lieu }}</flux:table.cell>
                            <flux:table.cell><x-tn.status-badge :etat="$item->etatStatut()">{{ Signalement::libelleStatut($item->statut) }}</x-tn.status-badge></flux:table.cell>
                            <flux:table.cell class="font-mono text-sm">{{ $item->soutiens_count }}</flux:table.cell>
                            @if ($this->voitTousLesSignalements)
                                <flux:table.cell>{{ $item->user?->name }}</flux:table.cell>
                            @endif
                            <flux:table.cell class="font-mono text-xs">{{ $item->created_at->format('d.m.Y') }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="eye" :href="route('signalements.show', $item)" wire:navigate aria-label="Voir" />
                                    @can('update', $item)
                                        <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('signalements.edit', $item)" wire:navigate aria-label="Modifier" />
                                    @endcan
                                    @can('delete', $item)
                                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="Supprimer ce signalement ?" aria-label="Supprimer" />
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
