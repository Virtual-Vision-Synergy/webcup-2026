<?php

use App\Models\Projet;
use App\Models\Quartier;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Projets de la ville (F67), page publique : décision assumée, l'information sur les travaux se consulte sans compte.
 * Création, modification et suppression : agents et admins uniquement (ProjetPolicy).
 */
new #[Layout('layouts::public'), Title('Projets de la ville')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $etat = '';

    #[Url(except: '')]
    public string $categorie = '';

    #[Url(except: '')]
    public string $quartier = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Projet::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'etat', 'categorie', 'quartier'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return Builder<Projet>
     */
    protected function filteredQuery(): Builder
    {
        $etat = in_array($this->etat, Projet::ETAT_OPTIONS, true) ? $this->etat : '';
        $categorie = in_array($this->categorie, Projet::CATEGORIE_OPTIONS, true) ? $this->categorie : '';
        $quartier = ctype_digit($this->quartier) ? (int) $this->quartier : null;
        $search = trim(mb_substr($this->search, 0, 100));

        return Projet::query()
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.$search.'%';
                $query->where(fn ($q) => $q->where('titre', 'like', $term)->orWhere('resume', 'like', $term)->orWhere('lieu', 'like', $term));
            })
            ->when($etat !== '', fn ($query) => $query->where('etat', $etat))
            ->when($categorie !== '', fn ($query) => $query->where('categorie', $categorie))
            ->when($quartier !== null, fn ($query) => $query->where('quartier_id', $quartier));
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with('quartier:id,nom')
            ->orderByRaw("case etat when 'en_cours' then 0 when 'etude' then 1 else 2 end")
            ->latest('updated_at')
            ->paginate(9);
    }

    /**
     * @return Collection<int, Quartier>
     */
    #[Computed]
    public function quartiers(): Collection
    {
        return Quartier::query()->orderBy('nom')->get(['id', 'nom']);
    }

    /**
     * Nombre de projets par état (bandeau de synthèse).
     *
     * @return array<string, int>
     */
    #[Computed]
    public function compteurs(): array
    {
        $parEtat = Projet::query()->selectRaw('etat, count(*) as total')->groupBy('etat')->pluck('total', 'etat');

        return collect(Projet::ETAT_OPTIONS)->mapWithKeys(fn (string $etat) => [$etat => (int) ($parEtat[$etat] ?? 0)])->all();
    }

    /**
     * Points de la carte : projets localisés qui correspondent aux filtres.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function points(): array
    {
        return $this->filteredQuery()
            ->geolocalises()
            ->limit(100)
            ->get()
            ->map(fn (Projet $projet) => [
                ...$projet->pointCarte($projet->titre, route('projets.show', $projet)) ?? [],
                'etat' => $projet->etatBadge(),
                'lignes' => array_filter([$projet->etatLabel(), $projet->lieu]),
                'lien' => __('Voir le projet'),
            ])
            ->all();
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->etat !== '' || $this->categorie !== '' || $this->quartier !== '';
    }

    public function resetFilters(): void
    {
        $this->authorize('viewAny', Projet::class);

        $this->reset('search', 'etat', 'categorie', 'quartier');
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $record = Projet::findOrFail($id);
        $this->authorize('delete', $record);

        $record->delete();

        Flux::toast(variant: 'success', text: __('Projet supprimé.'));
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6 @guest px-4 py-6 lg:px-8 @endguest">
    <x-tn.page-header
        label="{{ __('Vie de la ville') }}"
        title="{{ __('Projets de la ville') }}"
        subtitle="{{ __('Ce que la ville construit ou répare près de chez vous : où, quand, pour combien, et où en sont les travaux.') }}"
        :breadcrumb="auth()->check() ? ['Mon espace' => route('dashboard'), 'Projets de la ville' => null] : ['Accueil' => route('home'), 'Projets de la ville' => null]"
    >
        <x-slot:actions>
            @can('create', Projet::class)
                <flux:button variant="primary" icon="plus" :href="route('projets.create')" wire:navigate>{{ __('Nouveau projet') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <dl class="grid grid-cols-3 gap-3">
        @foreach (Projet::ETAT_LABELS as $value => $label)
            <div class="rounded-md border border-line bg-surface p-4">
                <dt class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-ink-2">{{ __($label) }}</dt>
                <dd class="mt-1 text-2xl font-semibold text-ink">{{ $this->compteurs[$value] }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Rechercher un projet, une rue…') }}" aria-label="{{ __('Rechercher un projet') }}" class="sm:max-w-xs" />
        <flux:select wire:model.live="etat" aria-label="{{ __('État du projet') }}" class="sm:max-w-40">
            <flux:select.option value="">{{ __('Tous les états') }}</flux:select.option>
            @foreach (Projet::ETAT_LABELS as $value => $label)
                <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="categorie" aria-label="{{ __('Type de projet') }}" class="sm:max-w-48">
            <flux:select.option value="">{{ __('Tous les types') }}</flux:select.option>
            @foreach (Projet::CATEGORIE_LABELS as $value => $label)
                <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="quartier" aria-label="{{ __('Quartier') }}" class="sm:max-w-40">
            <flux:select.option value="">{{ __('Tous les quartiers') }}</flux:select.option>
            @foreach ($this->quartiers as $q)
                <flux:select.option :value="$q->id">{{ $q->nom }}</flux:select.option>
            @endforeach
        </flux:select>
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">{{ __('Mise à jour…') }}</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="building-office-2" title="{{ $this->hasFilters() ? __('Aucun projet ne correspond') : __('Aucun projet pour le moment') }}" text="{{ $this->hasFilters() ? __('Retirez un filtre ou essayez un autre mot.') : __('Les projets de la ville seront publiés ici dès leur lancement.') }}">
            @if ($this->hasFilters())
                <flux:button size="sm" wire:click="resetFilters">{{ __('Réinitialiser la recherche') }}</flux:button>
            @endif
        </x-tn.empty>
    @else
        <ul class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->items as $item)
                <li wire:key="projet-{{ $item->id }}" class="group relative flex min-w-0 flex-col rounded-md border border-line bg-surface p-5 transition-colors hover:border-cyan/40">
                    <div class="flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-sm border border-cyan/25 bg-cyan/10 text-cyan" aria-hidden="true">
                            <flux:icon :name="$item->categorieIcone()" class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="font-semibold text-ink">
                                <a href="{{ route('projets.show', $item) }}" wire:navigate class="after:absolute after:inset-0 group-hover:text-cyan">{{ $item->titre }}</a>
                            </h2>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <span class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-ink-2">{{ $item->categorieLabel() }} · {{ $item->nomQuartier() }}</span>
                            </div>
                        </div>
                    </div>

                    <p class="mt-3 line-clamp-3 text-sm text-ink-2">{{ $item->resume }}</p>

                    <div class="mt-4 space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <x-tn.status-badge :etat="$item->etatBadge()">{{ $item->etatLabel() }}</x-tn.status-badge>
                            <span class="font-mono text-xs text-ink-2">{{ $item->avancement }} %</span>
                        </div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="{{ $item->avancement }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Avancement') }}">
                            <div class="h-full rounded-full bg-cyan" style="width: {{ $item->avancement }}%"></div>
                        </div>
                    </div>

                    <dl class="mt-4 space-y-1.5 border-t border-line pt-3 text-sm">
                        @if ($item->periode())
                            <div class="flex min-w-0 gap-2"><dt class="sr-only">{{ __('Dates') }}</dt><flux:icon name="calendar" class="mt-0.5 size-4 shrink-0 text-ink-2" /><dd class="min-w-0 truncate font-mono text-xs leading-5 text-ink-2">{{ $item->periode() }}</dd></div>
                        @endif
                        @if ($item->budgetFormate())
                            <div class="flex min-w-0 gap-2"><dt class="sr-only">{{ __('Budget') }}</dt><flux:icon name="banknotes" class="mt-0.5 size-4 shrink-0 text-ink-2" /><dd class="min-w-0 truncate font-mono text-xs leading-5 text-ink-2">{{ $item->budgetFormate() }}</dd></div>
                        @endif
                    </dl>

                    @canany(['update', 'delete'], $item)
                        <div class="relative z-10 mt-3 flex justify-end gap-1">
                            @can('update', $item)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('projets.edit', $item)" wire:navigate aria-label="{{ __('Modifier le projet :titre', ['titre' => $item->titre]) }}" />
                            @endcan
                            @can('delete', $item)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="{{ __('Supprimer ce projet ?') }}" aria-label="{{ __('Supprimer le projet :titre', ['titre' => $item->titre]) }}" />
                            @endcan
                        </div>
                    @endcanany
                </li>
            @endforeach
        </ul>

        <div>{{ $this->items->links() }}</div>

        @if ($this->points !== [])
            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-4">{{ __('Les projets sur la carte') }}</x-tn.section-label>
                <x-carte :points="$this->points" hauteur="24rem" :label="__('Carte des projets de la ville')" />
            </x-tn.surface>
        @endif
    @endif
</section>
