<?php

use App\Models\Service;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
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

    #[Url(except: '')]
    public string $categorie = '';

    #[Url(except: false)]
    public bool $mine = false;

    /** F45 : affichage en liste (cartes paginées) ou sur la carte des lieux d'accueil. */
    #[Url(except: 'liste')]
    public string $vue = 'liste';

    public function mount(): void
    {
        $this->authorize('viewAny', Service::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategorie(): void
    {
        $this->resetPage();
    }

    public function updatedMine(): void
    {
        $this->resetPage();
    }

    public function afficher(string $vue): void
    {
        $this->authorize('viewAny', Service::class);

        $this->vue = $vue === 'carte' ? 'carte' : 'liste';
    }

    public function enCarte(): bool
    {
        return $this->vue === 'carte';
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'categorie', 'mine');
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return trim($this->search) !== '' || $this->categorie !== '' || $this->mine;
    }

    /**
     * Recherche et filtres, partagés par la liste (et la carte si l'entité a des coordonnées).
     *
     * @return Builder<Service>
     */
    protected function filteredQuery(): Builder
    {
        return Service::query()
            ->when(trim($this->search) !== '', function ($query) {
                $search = trim($this->search);
                $term = '%'.$search.'%';
                $categories = Service::categoriesCorrespondant($search);
                $query->where(fn ($q) => $q->where('nom', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->when($categories !== [], fn ($q) => $q->orWhereIn('categorie', $categories)));
            })
            // Une valeur inconnue (URL modifiée à la main) est ignorée.
            ->when(in_array($this->categorie, Service::CATEGORIE_OPTIONS, true), fn ($query) => $query->where('categorie', $this->categorie))
            ->when($this->mine, fn ($query) => $query->whereBelongsTo(auth()->user()));
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with('user')
            ->prioritaires()
            ->paginate(10);
    }

    /**
     * Services prioritaires (F28) : mis en avant par un agent, affichés en tête du catalogue.
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function prioritaires(): Collection
    {
        return Service::query()->where('mis_en_avant', true)->orderBy('nom')->limit(6)->get();
    }

    /**
     * Bloc « Services prioritaires » : seulement sur la liste complète, en première page.
     */
    public function afficherPrioritaires(): bool
    {
        return ! $this->enCarte() && ! $this->hasFilters() && (int) $this->getPage() === 1 && $this->prioritaires->isNotEmpty();
    }

    /**
     * Lieux d'accueil localisés qui correspondent aux filtres (carte et liste textuelle équivalente).
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function lieux(): Collection
    {
        return $this->filteredQuery()
            ->geolocalises()
            ->prioritaires()
            ->limit(200)
            ->get();
    }

    /**
     * Points de la carte : nom, adresse, horaires et lien vers la fiche du service.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function points(): array
    {
        return $this->lieux
            ->map(fn (Service $service): array => [
                ...(array) $service->pointCarte(__($service->nom), route('services.show', $service)),
                'lignes' => array_filter([
                    $service->adresse ? __($service->adresse) : null,
                    $service->horaires ? __($service->horaires) : null,
                ]),
                'lien' => __('Voir la fiche du service'),
            ])
            ->values()
            ->all();
    }

    public function toggleFeatured(int $id): void
    {
        $record = Service::findOrFail($id);
        $this->authorize('feature', $record);

        $record->mis_en_avant = ! $record->mis_en_avant;
        $record->save();

        Cache::forget('landing.etat');

        Flux::toast(variant: 'success', text: $record->mis_en_avant ? __('Service mis en avant.') : __('Service retiré de la mise en avant.'));
    }

    public function delete(int $id): void
    {
        $record = Service::findOrFail($id);
        $this->authorize('delete', $record);

        $record->delete();

        Flux::toast(variant: 'success', text: __('Service supprimé(e).'));
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        :label="__('Annuaire')"
        :title="__('Services municipaux')"
        :subtitle="__(':n service(s) référencé(s)', ['n' => $this->items->total()])"
        :breadcrumb="[__('Mon espace') => route('dashboard'), __('Services') => null]"
    >
        <x-slot:actions>
            @can('create', Service::class)
                <flux:button variant="primary" icon="plus" :href="route('services.create')" wire:navigate>{{ __('Ajouter') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Rechercher un service (ex. santé)…') }}" aria-label="{{ __('Rechercher un service') }}" clearable class="sm:max-w-sm" />
        <flux:select wire:model.live="categorie" aria-label="{{ __('Filtrer par catégorie') }}" class="sm:max-w-60">
            <flux:select.option value="">{{ __('Toutes les catégories') }}</flux:select.option>
            @foreach (Service::CATEGORIE_LABELS as $valeur => $label)
                <flux:select.option value="{{ $valeur }}">{{ __($label) }}</flux:select.option>
            @endforeach
        </flux:select>
        @can('create', Service::class)
            <flux:checkbox wire:model.live="mine" label="{{ __('Mes services uniquement') }}" />
        @endcan
        <div class="flex gap-1 sm:ms-auto" role="group" aria-label="{{ __('Mode d\'affichage') }}">
            <flux:button size="sm" icon="list-bullet" :variant="$this->enCarte() ? 'ghost' : 'filled'" wire:click="afficher('liste')" aria-pressed="{{ $this->enCarte() ? 'false' : 'true' }}">{{ __('Liste') }}</flux:button>
            <flux:button size="sm" icon="map" :variant="$this->enCarte() ? 'filled' : 'ghost'" wire:click="afficher('carte')" aria-pressed="{{ $this->enCarte() ? 'true' : 'false' }}">{{ __('Carte') }}</flux:button>
        </div>
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">{{ __('Mise à jour…') }}</span>
    </div>

    @if ($this->afficherPrioritaires())
        <section aria-labelledby="titre-prioritaires" class="rounded-md border border-cyan/40 bg-cyan/5 p-4 md:p-5">
            <h2 id="titre-prioritaires" class="flex items-center gap-2 font-semibold text-ink">
                <flux:icon name="star" variant="solid" class="size-5 text-cyan" aria-hidden="true" />
                {{ __('Services prioritaires') }}
            </h2>
            <p class="mt-1 text-sm text-ink-2">{{ __('Les démarches les plus demandées, à portée de main.') }}</p>
            <ul class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->prioritaires as $prioritaire)
                    <li wire:key="prioritaire-{{ $prioritaire->id }}">
                        <a href="{{ route('services.show', $prioritaire) }}" wire:navigate class="group flex min-h-11 items-center gap-3 rounded-sm border border-line bg-surface px-3 py-2 transition-colors hover:border-cyan/40">
                            <flux:icon name="landmark" class="size-4 shrink-0 text-cyan" aria-hidden="true" />
                            <span class="min-w-0 flex-1 truncate font-medium text-ink group-hover:text-cyan">{{ __($prioritaire->nom) }}</span>
                            @if ($prioritaire->estIndisponible())
                                <flux:badge size="sm" color="red">{{ __('Indisponible') }}</flux:badge>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($this->enCarte())
        @if ($this->lieux->isEmpty())
            <x-tn.empty icon="map" title="{{ __('Aucun lieu à afficher') }}" text="{{ $this->hasFilters() ? __('Aucun service localisé ne correspond à cette recherche ou cette catégorie.') : __('Les lieux d\'accueil des services seront bientôt placés sur la carte.') }}">
                @if ($this->hasFilters())
                    <flux:button variant="primary" icon="x-mark" wire:click="resetFilters">{{ __('Effacer les filtres') }}</flux:button>
                @endif
            </x-tn.empty>
        @else
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <x-carte :points="$this->points" hauteur="28rem" :label="__('Carte des services physiques de la ville')" />

                <section aria-labelledby="liste-lieux" class="min-w-0 rounded-md border border-line bg-surface">
                    <h2 id="liste-lieux" class="border-b border-line px-4 py-3 font-semibold text-ink">
                        {{ __(':n lieu(x) sur la carte', ['n' => $this->lieux->count()]) }}
                        <span class="block text-xs font-normal text-ink-2">{{ __('Version texte de la carte : mêmes lieux, mêmes informations.') }}</span>
                    </h2>
                    <ul class="divide-y divide-line lg:max-h-[24.5rem] lg:overflow-y-auto">
                        @foreach ($this->lieux as $lieu)
                            <li wire:key="lieu-{{ $lieu->id }}" class="space-y-1 px-4 py-3 text-sm">
                                <a href="{{ route('services.show', $lieu) }}" wire:navigate class="font-semibold text-ink hover:text-cyan hover:underline">{{ __($lieu->nom) }}</a>
                                @if ($lieu->categorie)
                                    <flux:badge size="sm" class="ms-1">{{ __(Service::labelCategorie($lieu->categorie)) }}</flux:badge>
                                @endif
                                @if ($lieu->adresse)
                                    <p class="flex gap-2 text-ink-2"><flux:icon name="map-pin" class="mt-0.5 size-4 shrink-0" /><span><span class="sr-only">{{ __('Adresse :') }}</span> {{ __($lieu->adresse) }}</span></p>
                                @endif
                                @if ($lieu->horaires)
                                    <p class="flex gap-2 text-ink-2"><flux:icon name="clock" class="mt-0.5 size-4 shrink-0" /><span class="whitespace-pre-line font-mono text-xs leading-5"><span class="sr-only">{{ __('Horaires :') }}</span> {{ __($lieu->horaires) }}</span></p>
                                @endif
                                <a href="https://www.openstreetmap.org/directions?to={{ $lieu->latitude }}%2C{{ $lieu->longitude }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-cyan hover:underline">
                                    <flux:icon name="arrow-top-right-on-square" class="size-4" />{{ __('Itinéraire') }}<span class="sr-only"> {{ __('vers :nom (nouvel onglet)', ['nom' => __($lieu->nom)]) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        @endif
    @elseif ($this->items->isEmpty() && $this->hasFilters())
        <x-tn.empty icon="magnifying-glass" title="{{ __('Aucun service ne correspond') }}" text="{{ __('Aucun résultat pour cette recherche ou cette catégorie. Essayez un autre mot (ex. « santé », « état civil ») ou affichez tout le catalogue.') }}">
            <flux:button variant="primary" icon="x-mark" wire:click="resetFilters">{{ __('Effacer les filtres') }}</flux:button>
        </x-tn.empty>
    @elseif ($this->items->isEmpty())
        <x-tn.empty icon="landmark" title="{{ __('Aucun service pour le moment') }}" text="{{ __('Revenez plus tard : l\'annuaire est en cours de publication.') }}" />
    @else
        <ul class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->items as $item)
                <li wire:key="row-{{ $item->id }}" @class(['group relative flex min-w-0 flex-col rounded-md border bg-surface p-5 transition-colors hover:border-cyan/40', 'border-cyan/40' => $item->mis_en_avant, 'border-line' => ! $item->mis_en_avant])>
                    <div class="mb-3 flex flex-wrap gap-1.5">
                        @if ($item->estIndisponible())
                            <flux:badge size="sm" color="red" icon="no-symbol">{{ __('Indisponible') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="green" icon="check-circle">{{ __('Disponible') }}</flux:badge>
                        @endif
                        @if ($item->mis_en_avant)
                            <flux:badge size="sm" color="cyan" icon="star">{{ __('Prioritaire') }}</flux:badge>
                        @endif
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-sm border border-cyan/18 bg-cyan/8 text-cyan" aria-hidden="true">
                            <flux:icon :name="Service::iconeCategorie($item->categorie)" class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="font-semibold text-ink">
                                <a href="{{ route('services.show', $item) }}" wire:navigate class="after:absolute after:inset-0 group-hover:text-cyan">{{ __($item->nom) }}</a>
                            </h2>
                            @if ($item->categorie)
                                <flux:badge size="sm" class="mt-1">{{ __(Service::labelCategorie($item->categorie)) }}</flux:badge>
                            @endif
                            @if ($item->description)
                                <p class="mt-1 line-clamp-2 text-sm text-ink-2">{{ __($item->description) }}</p>
                            @endif
                        </div>
                    </div>
                    @if ($item->estIndisponible())
                        <p class="mt-3 rounded-sm border border-magenta/35 bg-magenta/8 px-3 py-2 text-sm text-magenta" role="status">
                            {{ $item->motif_indisponibilite ?: __('Service momentanément indisponible.') }}
                            <span class="block text-xs">
                                {{ $item->retour_prevu_le ? __('Retour prévu le :date.', ['date' => $item->retour_prevu_le->translatedFormat('j F Y')]) : __('Date de retour à confirmer.') }}
                            </span>
                        </p>
                    @endif
                    <dl class="mt-4 space-y-1.5 border-t border-line pt-3 text-sm">
                        @if ($item->horaires)
                            <div class="flex min-w-0 gap-2"><dt class="sr-only">{{ __('Horaires') }}</dt><flux:icon name="clock" class="mt-0.5 size-4 shrink-0 text-ink-2" /><dd class="min-w-0 truncate font-mono text-xs leading-5 text-ink-2">{{ \Illuminate\Support\Str::before(__($item->horaires), "\n") }}</dd></div>
                        @endif
                        @if ($item->telephone)
                            <div class="flex gap-2"><dt class="sr-only">{{ __('Téléphone') }}</dt><flux:icon name="phone" class="mt-0.5 size-4 shrink-0 text-ink-2" /><dd class="font-mono text-xs leading-5 text-ink-2">{{ $item->telephone }}</dd></div>
                        @endif
                    </dl>
                    @canany(['feature', 'update', 'delete'], $item)
                        <div class="relative z-10 mt-3 flex justify-end gap-1">
                            @can('feature', $item)
                                <flux:button size="sm" variant="ghost" icon="star" :icon:variant="$item->mis_en_avant ? 'solid' : 'outline'" wire:click="toggleFeatured({{ $item->id }})" :aria-label="$item->mis_en_avant ? __('Retirer la mise en avant de :nom', ['nom' => __($item->nom)]) : __('Mettre en avant :nom', ['nom' => __($item->nom)])" :title="$item->mis_en_avant ? __('Retirer la mise en avant') : __('Mettre en avant')" />
                            @endcan
                            @can('update', $item)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('services.edit', $item)" wire:navigate aria-label="{{ __('Modifier :nom', ['nom' => __($item->nom)]) }}" />
                            @endcan
                            @can('delete', $item)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="{{ __('Supprimer ce service ?') }}" aria-label="{{ __('Supprimer :nom', ['nom' => __($item->nom)]) }}" />
                            @endcan
                        </div>
                    @endcanany
                </li>
            @endforeach
        </ul>

        <div>{{ $this->items->links() }}</div>
    @endif
</section>
