<?php

use App\Models\AbonnementLigne;
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
            ->when($this->perturbees, fn ($query) => $query->where(fn ($q) => $q->where('etat', '!=', 'normal')
                ->orWhereHas('interruptions', fn ($i) => $i->enCours())));
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with('interruptionsEnCours')
            ->orderByRaw("case when etat = 'normal' then 1 else 0 end")
            ->orderBy('numero')
            ->limit(30)
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
            ->where(fn ($q) => $q->where('etat', '!=', 'normal')->orWhereHas('interruptions', fn ($i) => $i->enCours()))
            ->with('interruptionsEnCours')
            ->orderByRaw("case when etat = 'interrompu' then 0 else 1 end")
            ->orderBy('numero')
            ->limit(30)
            ->get()
            ->sortBy(fn (LigneTransport $ligne): int => $ligne->etatAffiche() === 'interrompu' ? 0 : 1)
            ->values();
    }

    /**
     * F97 : trajets habituels de l'utilisateur connecté uniquement (jamais d'identifiant venant du navigateur).
     *
     * @return Collection<int, AbonnementLigne>
     */
    #[Computed]
    public function mesTrajets(): Collection
    {
        return AbonnementLigne::query()
            ->whereBelongsTo(auth()->user())
            ->with('ligne.interruptionsEnCours')
            ->latest()
            ->limit(10)
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

        Flux::toast(variant: 'success', text: __('Ligne supprimée.'));
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        label="{{ __('Mobilité') }}"
        title="{{ __('Transports municipaux') }}"
        :subtitle="$this->perturbations->isEmpty() ? __('Trafic normal sur tout le réseau') : __(':n ligne(s) perturbée(s) en ce moment', ['n' => $this->perturbations->count()])"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Transports' => null]"
    >
        <x-slot:actions>
            @can('create', LigneTransport::class)
                <flux:button variant="primary" icon="plus" :href="route('transports.create')" wire:navigate>{{ __('Ajouter une ligne') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <section aria-labelledby="titre-mes-trajets" class="space-y-3" data-test="mes-trajets">
        <h2 id="titre-mes-trajets" class="tn-display text-lg font-semibold text-ink">{{ __('Mes trajets habituels') }}</h2>
        @forelse ($this->mesTrajets as $trajet)
            @php($interruptionTrajet = $trajet->ligne->interruptionCourante())
            @if ($interruptionTrajet)
                <x-transport-interruption wire:key="trajet-{{ $trajet->id }}" :interruption="$interruptionTrajet" :ligne="$trajet->ligne" :arret="$trajet->arret" personnel />
            @else
                <p wire:key="trajet-{{ $trajet->id }}" class="flex flex-wrap items-center gap-2 rounded-md border border-line bg-surface px-4 py-3 text-sm">
                    <flux:icon name="check-circle" class="size-4 shrink-0 text-ink-2" aria-hidden="true" />
                    <a href="{{ route('transports.show', $trajet->ligne) }}" wire:navigate class="font-mono font-semibold text-ink hover:text-cyan">Ligne {{ $trajet->ligne->numero }}</a>
                    @if ($trajet->arret)
                        <span class="text-ink-2">· {{ __('arrêt :arret', ['arret' => $trajet->arret]) }}</span>
                    @endif
                    <x-tn.status-badge :etat="$trajet->ligne->etatBadge()">{{ $trajet->ligne->etatLabel() }}</x-tn.status-badge>
                </p>
            @endif
        @empty
            <p class="rounded-md border border-dashed border-line px-4 py-3 text-sm text-ink-2">
                <flux:icon name="bell" class="me-1 inline size-4 text-cyan" aria-hidden="true" />
                {{ __('Ouvrez la fiche de la ligne que vous prenez et choisissez votre arrêt : si elle est interrompue, vous serez prévenu et on vous dira comment faire.') }}
            </p>
        @endforelse
    </section>

    @if ($this->perturbations->isNotEmpty())
        <div role="alert" class="space-y-2 rounded-md border border-amber/40 bg-amber/8 p-4">
            <p class="flex items-center gap-2 font-semibold text-amber">
                <flux:icon name="exclamation-triangle" class="size-5" />
                {{ __('Perturbations en cours') }}
            </p>
            <ul class="space-y-2">
                @foreach ($this->perturbations as $ligne)
                    <li wire:key="perturbation-{{ $ligne->id }}" class="flex flex-col gap-1 text-sm sm:flex-row sm:items-start sm:gap-3">
                        <span class="flex shrink-0 items-center gap-2">
                            <x-tn.status-badge :etat="$ligne->etatBadge()">{{ $ligne->etatLabel() }}</x-tn.status-badge>
                            <a href="{{ route('transports.show', $ligne) }}" wire:navigate class="font-mono font-semibold text-ink hover:text-cyan">Ligne {{ $ligne->numero }}</a>
                        </span>
                        <span class="text-ink-2">
                            {{ $ligne->messagePerturbation() ?? __('Perturbation signalée, informations à venir.') }}
                            @if ($ligne->interruptionCourante())
                                <a href="{{ route('transports.show', $ligne) }}" wire:navigate class="ms-1 font-semibold text-magenta underline underline-offset-2">{{ __('Voir comment faire') }}</a>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Ligne ou arrêt (ex. 4, Marché, Université)…') }}" aria-label="{{ __('Rechercher une ligne ou un arrêt') }}" class="sm:max-w-sm" />
        <flux:select wire:model.live="mode" aria-label="{{ __('Mode de transport') }}" class="sm:max-w-44">
            <flux:select.option value="">{{ __('Tous les modes') }}</flux:select.option>
            @foreach (LigneTransport::MODE_LABELS as $value => $label)
                <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:checkbox wire:model.live="perturbees" label="{{ __('Lignes perturbées uniquement') }}" />
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">{{ __('Mise à jour…') }}</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="bus" title="{{ __('Aucune ligne trouvée') }}" :text="__('Vérifiez l\'orthographe de l\'arrêt ou retirez un filtre.')">
            @if ($search !== '' || $mode !== '' || $perturbees)
                <flux:button size="sm" wire:click="resetFilters">{{ __('Réinitialiser la recherche') }}</flux:button>
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
                    'border-amber/50' => $item->etatAffiche() === 'perturbe',
                    'border-magenta/50' => $item->etatAffiche() === 'interrompu',
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

                    @if ($item->estPerturbee() && $item->messagePerturbation())
                        <p class="mt-3 flex items-start gap-1.5 text-sm {{ $item->etatAffiche() === 'interrompu' ? 'text-magenta' : 'text-amber' }}">
                            <flux:icon :name="$item->etatAffiche() === 'interrompu' ? 'x-circle' : 'exclamation-triangle'" variant="micro" class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            <span class="line-clamp-3">{{ $item->messagePerturbation() }}</span>
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
                            <span class="text-ink-2">{{ __('Dessert :') }}</span>
                            @foreach ($trouves as $arret)
                                <span class="rounded-xs border border-cyan/35 bg-cyan/8 px-1.5 py-0.5 text-cyan">{{ $arret }}</span>
                            @endforeach
                        </p>
                    @endif

                    <dl class="mt-4 space-y-1.5 border-t border-line pt-3 text-sm">
                        <div class="flex min-w-0 gap-2"><dt class="sr-only">{{ __('Horaires') }}</dt><flux:icon name="clock" class="mt-0.5 size-4 shrink-0 text-ink-2" /><dd class="min-w-0 truncate font-mono text-xs leading-5 text-ink-2">{{ \Illuminate\Support\Str::before($item->horaires, "\n") }}</dd></div>
                        @if ($item->frequence)
                            <div class="flex min-w-0 gap-2"><dt class="sr-only">{{ __('Fréquence') }}</dt><flux:icon name="arrow-path" class="mt-0.5 size-4 shrink-0 text-ink-2" /><dd class="min-w-0 truncate font-mono text-xs leading-5 text-ink-2">{{ $item->frequence }}</dd></div>
                        @endif
                    </dl>

                    @canany(['update', 'delete'], $item)
                        <div class="relative z-10 mt-3 flex justify-end gap-1">
                            @can('update', $item)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('transports.edit', $item)" wire:navigate aria-label="{{ __('Modifier la ligne :numero', ['numero' => $item->numero]) }}" />
                            @endcan
                            @can('delete', $item)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="{{ __('Supprimer cette ligne ?') }}" aria-label="{{ __('Supprimer la ligne :numero', ['numero' => $item->numero]) }}" />
                            @endcan
                        </div>
                    @endcanany
                </li>
            @endforeach
        </ul>

        <div>{{ $this->items->links() }}</div>
    @endif
</section>
