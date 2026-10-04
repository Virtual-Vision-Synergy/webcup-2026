<?php

use App\Models\Partner;
use App\Models\PartnerOffering;
use App\Models\Service;
use App\Services\OrientationServices;
use App\Support\VersionSimple;
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

    /** F38 / F64 : n'afficher que les services disponibles (ni interrompus, ni indisponibles, ni perturbés). */
    #[Url(except: false)]
    public bool $disponibles = false;

    /** F99 : statut Disponible ET ouvert à l'instant (services partenaires : horaires ; services municipaux : pleinement disponibles). */
    #[Url(except: false)]
    public bool $maintenant = false;

    /** F99 : « Tous / Services municipaux / Services partenaires ». */
    #[Url(except: 'tous')]
    public string $type = 'tous';

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

    public function updatedDisponibles(): void
    {
        $this->resetPage();
    }

    public function updatedMaintenant(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    /**
     * F99 : type de catalogue demandé ; une valeur inconnue (URL modifiée à la main) vaut « tous ».
     */
    public function typeCatalogue(): string
    {
        return array_key_exists($this->type, PartnerOffering::TYPE_CATALOGUE_OPTIONS) ? $this->type : 'tous';
    }

    public function afficheMunicipaux(): bool
    {
        return $this->typeCatalogue() !== 'partenaires';
    }

    public function affichePartenaires(): bool
    {
        // « Mes services uniquement » concerne les services municipaux d'un agent.
        return $this->typeCatalogue() !== 'municipaux' && ! $this->mine && ! $this->enCarte();
    }

    /**
     * F99 : services partenaires publiés (partenaire publié), mêmes recherche et filtres que le catalogue.
     * « Disponible maintenant » : statut Disponible ET ouvert à l'instant selon les horaires (openingStatus, F74).
     *
     * @return \Illuminate\Support\Collection<int, PartnerOffering>
     */
    #[Computed]
    public function offresPartenaires(): \Illuminate\Support\Collection
    {
        if (! $this->affichePartenaires()) {
            return collect();
        }

        $search = trim($this->search);
        $categorie = in_array($this->categorie, Partner::TYPE_OPTIONS, true) ? $this->categorie : null;

        // Catégorie municipale sans équivalent chez les partenaires : aucun service partenaire.
        if ($this->categorie !== '' && $categorie === null) {
            return collect();
        }

        $offres = PartnerOffering::query()
            ->published()
            ->with('partner')
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.$search.'%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('partner', fn ($p) => $p->where('name', 'like', $term)));
            })
            ->when($categorie !== null, fn ($query) => $query->whereHas('partner', fn ($p) => $p->where('type', $categorie)))
            ->when($this->disponibles || $this->maintenant, fn ($query) => $query->where('status', PartnerOffering::STATUS_AVAILABLE))
            ->orderByRaw("case status when 'available' then 0 when 'full' then 1 else 2 end")
            ->orderBy('title')
            ->limit(30)
            ->get();

        return $this->maintenant
            ? $offres->filter(fn (PartnerOffering $offre): bool => $offre->estDisponibleMaintenant())->values()
            : $offres;
    }

    public function afficher(string $vue): void
    {
        $this->authorize('viewAny', Service::class);

        $this->vue = $vue === 'carte' ? 'carte' : 'liste';
    }

    public function enCarte(): bool
    {
        // F62 : en version simple, pas de carte (ni Leaflet) : la liste garde les mêmes informations.
        return $this->vue === 'carte' && ! VersionSimple::actif();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'categorie', 'mine', 'disponibles', 'maintenant', 'type');
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return trim($this->search) !== '' || $this->categorie !== '' || $this->mine || $this->disponibles || $this->maintenant || $this->typeCatalogue() !== 'tous';
    }

    /**
     * D10 : orientation tolérante (fautes, accents, pluriels, synonymes de l'admin), calculée une fois par requête.
     *
     * @return array{resultats: list<array{id: int, score: int, raisons: list<string>}>, exact: bool, suggestion: string|null}|null
     */
    #[Computed]
    public function orientation(): ?array
    {
        return trim($this->search) === '' ? null : app(OrientationServices::class)->rechercher(trim($this->search));
    }

    /**
     * D10 : identifiants trouvés, du plus pertinent au moins pertinent.
     *
     * @return list<int>
     */
    protected function idsOrientes(): array
    {
        return array_column($this->orientation['resultats'] ?? [], 'id');
    }

    /**
     * D10 : raisons affichées sous un résultat (« Correspond à : poubelle »).
     *
     * @return list<string>
     */
    public function raisons(int $serviceId): array
    {
        foreach ($this->orientation['resultats'] ?? [] as $resultat) {
            if ($resultat['id'] === $serviceId) {
                return array_slice($resultat['raisons'], 0, 3);
            }
        }

        return [];
    }

    /**
     * D10 : aucun mot n'a été reconnu tel quel ; les résultats sont les services les plus proches.
     */
    public function resultatsApproches(): bool
    {
        return $this->orientation !== null && ! $this->orientation['exact'] && $this->idsOrientes() !== [];
    }

    public function utiliserSuggestion(): void
    {
        $this->authorize('viewAny', Service::class);

        $suggestion = $this->orientation['suggestion'] ?? null;

        if ($suggestion !== null) {
            $this->search = $suggestion;
            unset($this->orientation);
            $this->resetPage();
        }
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
                // D10 : services trouvés par l'orientation tolérante, en plus de la recherche exacte (F32).
                $ids = $this->idsOrientes();
                $query->where(fn ($q) => $q->where('nom', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    // F27 : un habitant peut aussi chercher avec les mots de la version traduite.
                    ->orWhereHas('translations', fn ($t) => $t->where('nom', 'like', $term)->orWhere('description', 'like', $term))
                    ->when($categories !== [], fn ($q) => $q->orWhereIn('categorie', $categories))
                    ->when($ids !== [], fn ($q) => $q->orWhereIn('id', $ids)));
            })
            // Une valeur inconnue (URL modifiée à la main) est ignorée.
            ->when(in_array($this->categorie, Service::CATEGORIE_OPTIONS, true), fn ($query) => $query->where('categorie', $this->categorie))
            ->when($this->mine, fn ($query) => $query->whereBelongsTo(auth()->user()))
            ->when($this->disponibles || $this->maintenant, fn ($query) => $query->pleinementDisponibles())
            // F99 : « Services partenaires » seuls → aucun service municipal.
            ->when(! $this->afficheMunicipaux(), fn ($query) => $query->whereRaw('1 = 0'));
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $ids = $this->idsOrientes();

        return $this->filteredQuery()
            ->with(['user', 'interruptionCourante'])
            ->avecTraduction()
            // D10 : les plus pertinents d'abord quand on cherche.
            ->when($ids !== [], fn ($query) => $query->orderByRaw(
                'case id '.implode(' ', array_fill(0, count($ids), 'when ? then ?')).' else ? end',
                [...array_merge(...array_map(fn (int $id, int $rang): array => [$id, $rang], $ids, array_keys($ids))), count($ids)],
            ))
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
        return Service::query()->with('interruptionCourante')->avecTraduction()->where('mis_en_avant', true)->orderBy('nom')->limit(6)->get();
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
            ->with('interruptionCourante')->avecTraduction()
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
                ...(array) $service->pointCarte($service->t('nom'), route('services.show', $service)),
                'lignes' => array_filter([
                    $service->adresse ? $service->t('adresse') : null,
                    $service->horaires ? $service->t('horaires') : null,
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
            <x-tn.version-simple />
            <flux:button variant="outline" icon="question-mark-circle" :href="route('orientation.index')" wire:navigate>{{ __('Je ne sais pas à qui m\'adresser') }}</flux:button>
            @can('create', Service::class)
                <flux:button variant="primary" icon="plus" :href="route('services.create')" wire:navigate>{{ __('Ajouter') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <x-tn.aide id="services-index">{{ __('Tapez le nom d\'un service dans la recherche, puis ouvrez sa fiche pour voir ses horaires et ses contacts.') }}</x-tn.aide>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Rechercher un service (ex. santé)…') }}" aria-label="{{ __('Rechercher un service') }}" clearable class="sm:max-w-sm" />
        <flux:select wire:model.live="categorie" aria-label="{{ __('Filtrer par catégorie') }}" class="sm:max-w-60">
            <flux:select.option value="">{{ __('Toutes les catégories') }}</flux:select.option>
            @foreach (Service::CATEGORIE_LABELS as $valeur => $label)
                <flux:select.option value="{{ $valeur }}">{{ __($label) }}</flux:select.option>
            @endforeach
        </flux:select>
        {{-- F99 : municipaux / partenaires / tous, et « Disponible maintenant » (filtres GET, combinables). --}}
        <flux:select wire:model.live="type" aria-label="{{ __('Type de service') }}" class="sm:max-w-56" data-test="filtre-type">
            @foreach (PartnerOffering::TYPE_CATALOGUE_OPTIONS as $valeur => $label)
                <flux:select.option value="{{ $valeur }}">{{ __($label) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:checkbox wire:model.live="disponibles" label="{{ __('Disponibles seulement') }}" />
        <flux:checkbox wire:model.live="maintenant" label="{{ __('Disponible maintenant') }}" data-test="filtre-maintenant" />
        @can('create', Service::class)
            <flux:checkbox wire:model.live="mine" label="{{ __('Mes services uniquement') }}" />
        @endcan
        @unless (VersionSimple::actif())
            <div class="flex gap-1 sm:ms-auto" role="group" aria-label="{{ __('Mode d\'affichage') }}">
                <flux:button size="sm" icon="list-bullet" :variant="$this->enCarte() ? 'ghost' : 'filled'" wire:click="afficher('liste')" aria-pressed="{{ $this->enCarte() ? 'false' : 'true' }}">{{ __('Liste') }}</flux:button>
                <flux:button size="sm" icon="map" :variant="$this->enCarte() ? 'filled' : 'ghost'" wire:click="afficher('carte')" aria-pressed="{{ $this->enCarte() ? 'true' : 'false' }}">{{ __('Carte') }}</flux:button>
            </div>
        @endunless
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">{{ __('Mise à jour…') }}</span>
    </div>

    {{-- D10 : « Vouliez-vous dire… » et résultats approchés : jamais une page vide quand on cherche. --}}
    @if (! $this->enCarte() && $this->orientation !== null)
        @if ($this->orientation['suggestion'] !== null && Str::lower(trim($search)) !== $this->orientation['suggestion'])
            <p class="text-sm text-ink-2" data-test="vouliez-vous-dire">
                {{ __('Vouliez-vous dire') }}
                <button type="button" wire:click="utiliserSuggestion" class="font-semibold text-cyan underline-offset-2 hover:underline">« {{ $this->orientation['suggestion'] }} »</button> ?
            </p>
        @endif
        @if ($this->resultatsApproches())
            <div class="flex items-start gap-2 rounded-md border border-amber/35 bg-amber/8 p-3 text-sm text-ink" role="status" data-test="resultats-approches">
                <flux:icon name="light-bulb" class="mt-0.5 size-5 shrink-0 text-amber" aria-hidden="true" />
                <p>{{ __('Aucun service ne correspond exactement à « :q ». Voici les services les plus proches ; en cas de doute, l’Accueil de la Mairie vous oriente.', ['q' => trim($search)]) }}</p>
            </div>
        @endif
    @endif

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
                            <span class="min-w-0 flex-1 truncate font-medium text-ink group-hover:text-cyan">{{ $prioritaire->t('nom') }}</span>
                            <x-service-status :service="$prioritaire" compact />
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- F99 : services proposés par les partenaires de la ville (badge « Partenaire », état en texte + icône, prochaine action). --}}
    @if ($this->affichePartenaires() && $this->offresPartenaires->isNotEmpty())
        <section aria-labelledby="titre-partenaires" class="space-y-3" data-test="services-partenaires">
            <h2 id="titre-partenaires" class="flex items-center gap-2 font-semibold text-ink">
                <flux:icon name="building-storefront" class="size-5 text-violet-400" aria-hidden="true" />
                {{ __('Services partenaires') }}
                <span class="text-sm font-normal text-ink-2">({{ $this->offresPartenaires->count() }})</span>
            </h2>
            <ul class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->offresPartenaires as $offre)
                    @php($ouverture = $offre->openingStatus())
                    <li wire:key="offre-{{ $offre->id }}" class="group relative flex min-w-0 flex-col rounded-md border border-line bg-surface p-5 transition-colors hover:border-cyan/40">
                        <div class="mb-3 flex flex-wrap gap-1.5">
                            <x-service-status :service="$offre" compact />
                            <flux:badge size="sm" color="violet" icon="building-storefront">{{ __('Partenaire') }}</flux:badge>
                        </div>
                        <h3 class="font-semibold text-ink">
                            <a href="{{ route('catalogue.partners.show', $offre) }}" wire:navigate class="after:absolute after:inset-0 group-hover:text-cyan">{{ __($offre->title) }}</a>
                        </h3>
                        <p class="text-sm text-ink-2">{{ __('Proposé par :partenaire', ['partenaire' => $offre->partner->name]) }}</p>
                        <p class="mt-1 line-clamp-2 text-sm text-ink-2">{{ __($offre->description) }}</p>
                        <dl class="mt-4 space-y-1.5 border-t border-line pt-3 text-sm">
                            <div class="flex gap-2"><dt class="sr-only">{{ __('Ouverture') }}</dt><flux:icon :name="$ouverture['open'] ? 'clock' : 'moon'" class="mt-0.5 size-4 shrink-0 text-ink-2" /><dd class="text-xs leading-5 text-ink-2">{{ $ouverture['label'] }}</dd></div>
                            <div class="flex gap-2"><dt class="sr-only">{{ __('Prochaine étape') }}</dt><flux:icon name="arrow-right-circle" class="mt-0.5 size-4 shrink-0 text-cyan" /><dd class="text-xs font-medium leading-5 text-cyan">{{ __($offre->prochaineAction()['principale']['label']) }}</dd></div>
                        </dl>
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
                                <a href="{{ route('services.show', $lieu) }}" wire:navigate class="font-semibold text-ink hover:text-cyan hover:underline">{{ $lieu->t('nom') }}</a>
                                <x-service-status :service="$lieu" compact class="ms-1" />
                                @if ($lieu->categorie)
                                    <flux:badge size="sm" class="ms-1">{{ __(Service::labelCategorie($lieu->categorie)) }}</flux:badge>
                                @endif
                                @if ($lieu->adresse)
                                    <p class="flex gap-2 text-ink-2"><flux:icon name="map-pin" class="mt-0.5 size-4 shrink-0" /><span><span class="sr-only">{{ __('Adresse :') }}</span> {{ $lieu->t('adresse') }}</span></p>
                                @endif
                                @if ($lieu->horaires)
                                    <p class="flex gap-2 text-ink-2"><flux:icon name="clock" class="mt-0.5 size-4 shrink-0" /><span class="whitespace-pre-line font-mono text-xs leading-5"><span class="sr-only">{{ __('Horaires :') }}</span> {{ $lieu->t('horaires') }}</span></p>
                                @endif
                                <a href="https://www.openstreetmap.org/directions?to={{ $lieu->latitude }}%2C{{ $lieu->longitude }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-cyan hover:underline">
                                    <flux:icon name="arrow-top-right-on-square" class="size-4" />{{ __('Itinéraire') }}<span class="sr-only"> {{ __('vers :nom (nouvel onglet)', ['nom' => $lieu->t('nom')]) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        @endif
    @elseif ($this->items->isEmpty() && $this->offresPartenaires->isEmpty() && $this->hasFilters())
        <x-tn.empty icon="magnifying-glass" title="{{ __('Aucun service ne correspond') }}" text="{{ __('Aucun résultat pour cette recherche ou cette catégorie. Essayez un autre mot (ex. « santé », « état civil ») ou affichez tout le catalogue.') }}">
            <flux:button variant="primary" icon="x-mark" wire:click="resetFilters">{{ __('Effacer les filtres') }}</flux:button>
        </x-tn.empty>
    @elseif ($this->items->isEmpty() && $this->offresPartenaires->isEmpty())
        <x-tn.empty icon="landmark" title="{{ __('Aucun service pour le moment') }}" text="{{ __('Revenez plus tard : l\'annuaire est en cours de publication.') }}" />
    @elseif ($this->items->isNotEmpty())
        @if ($this->offresPartenaires->isNotEmpty())
            <h2 class="flex items-center gap-2 font-semibold text-ink"><flux:icon name="landmark" class="size-5 text-cyan" aria-hidden="true" />{{ __('Services municipaux') }}</h2>
        @endif
        <ul class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->items as $item)
                <li wire:key="row-{{ $item->id }}" @class(['group relative flex min-w-0 flex-col rounded-md border bg-surface p-5 transition-colors hover:border-cyan/40', 'border-cyan/40' => $item->mis_en_avant, 'border-line' => ! $item->mis_en_avant])>
                    <div class="mb-3 flex flex-wrap gap-1.5">
                        <x-service-status :service="$item" compact />
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
                                <a href="{{ route('services.show', $item) }}" wire:navigate class="after:absolute after:inset-0 group-hover:text-cyan">{{ $item->t('nom') }}</a>
                            </h2>
                            @if ($item->categorie)
                                <flux:badge size="sm" class="mt-1">{{ __(Service::labelCategorie($item->categorie)) }}</flux:badge>
                            @endif
                            @if ($item->description)
                                <p class="mt-1 line-clamp-2 text-sm text-ink-2">{{ $item->t('description') }}</p>
                            @endif
                            {{-- F27 : fiche pas encore traduite dans la langue choisie (repli sur le français). --}}
                            @if ($item->afficheEnFrancais(['nom', 'description']))
                                <x-contenu-en-francais compact class="mt-1" />
                            @endif
                            @if ($this->raisons($item->id) !== [])
                                <p class="mt-1 text-xs text-ink-2" data-test="raisons">{{ __('Correspond à :') }} {{ implode(', ', array_map(fn (string $r): string => '« '.$r.' »', $this->raisons($item->id))) }}</p>
                            @endif
                        </div>
                    </div>
                    @if ($item->etat() !== Service::ETAT_DISPONIBLE)
                        <p @class([
                            'mt-3 rounded-sm border px-3 py-2 text-sm',
                            'border-magenta/35 bg-magenta/8 text-magenta' => $item->estIndisponible(),
                            'border-amber/35 bg-amber/8 text-amber' => $item->estPerturbe(),
                        ])>
                            {{ $item->motifEtat() ?: ($item->estIndisponible() ? __('Service momentanément indisponible.') : __('Délais allongés.')) }}
                            <span class="block text-xs font-medium">{{ $item->libelleRetourPrevu() }}</span>
                        </p>
                    @endif
                    <dl class="mt-4 space-y-1.5 border-t border-line pt-3 text-sm">
                        @if ($item->horaires)
                            <div class="flex min-w-0 gap-2"><dt class="sr-only">{{ __('Horaires') }}</dt><flux:icon name="clock" class="mt-0.5 size-4 shrink-0 text-ink-2" /><dd class="min-w-0 truncate font-mono text-xs leading-5 text-ink-2">{{ \Illuminate\Support\Str::before((string) $item->t('horaires'), "\n") }}</dd></div>
                        @endif
                        @if ($item->telephone)
                            <div class="flex gap-2"><dt class="sr-only">{{ __('Téléphone') }}</dt><flux:icon name="phone" class="mt-0.5 size-4 shrink-0 text-ink-2" /><dd class="font-mono text-xs leading-5 text-ink-2">{{ $item->telephone }}</dd></div>
                        @endif
                    </dl>
                    @canany(['feature', 'update', 'delete'], $item)
                        <div class="relative z-10 mt-3 flex justify-end gap-1">
                            @can('feature', $item)
                                <flux:button size="sm" variant="ghost" icon="star" :icon:variant="$item->mis_en_avant ? 'solid' : 'outline'" wire:click="toggleFeatured({{ $item->id }})" :aria-label="$item->mis_en_avant ? __('Retirer la mise en avant de :nom', ['nom' => $item->t('nom')]) : __('Mettre en avant :nom', ['nom' => $item->t('nom')])" :title="$item->mis_en_avant ? __('Retirer la mise en avant') : __('Mettre en avant')" />
                            @endcan
                            @can('update', $item)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('services.edit', $item)" wire:navigate aria-label="{{ __('Modifier :nom', ['nom' => $item->t('nom')]) }}" />
                            @endcan
                            @can('delete', $item)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="{{ __('Supprimer ce service ?') }}" aria-label="{{ __('Supprimer :nom', ['nom' => $item->t('nom')]) }}" />
                            @endcan
                        </div>
                    @endcanany
                </li>
            @endforeach
        </ul>

        <div>{{ $this->items->links() }}</div>
    @endif
</section>
