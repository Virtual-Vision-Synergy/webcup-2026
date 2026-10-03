<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\Idea;
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
 * Boîte à idées (F68), page publique : décision assumée, les idées publiées se consultent sans compte.
 * Proposer et soutenir exigent d'être connecté ; les idées masquées par la modération n'apparaissent pas.
 */
new #[Layout('layouts::public'), Title('Boîte à idées')] class extends Component {
    use ThrottlesPerUser, WithPagination;

    public const TRI_OPTIONS = ['soutiens' => 'Les plus soutenues', 'recentes' => 'Les plus récentes'];

    #[Url(except: 'soutiens')]
    public string $tri = 'soutiens';

    #[Url(except: '')]
    public string $filterCategory = '';

    #[Url(except: '')]
    public string $filterStatus = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Idea::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['tri', 'filterCategory', 'filterStatus'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return Builder<Idea>
     */
    protected function filteredQuery(): Builder
    {
        return Idea::query()
            ->visibles()
            ->when(in_array($this->filterCategory, Idea::CATEGORY_OPTIONS, true), fn ($query) => $query->where('category', $this->filterCategory))
            ->when(in_array($this->filterStatus, Idea::STATUS_OPTIONS, true), fn ($query) => $query->where('status', $this->filterStatus));
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->withCount('soutiens')
            ->withExists(['soutiens as soutenu_par_moi' => fn ($query) => $query->where('user_id', auth()->id())])
            ->when($this->tri !== 'recentes', fn ($query) => $query->orderByDesc('soutiens_count'))
            ->latest()
            ->latest('id')
            ->paginate(10);
    }

    /**
     * Mise en avant : les dernières idées retenues par la ville.
     *
     * @return Collection<int, Idea>
     */
    #[Computed]
    public function retenues(): Collection
    {
        return Idea::query()->visibles()->where('status', 'retenue')->withCount('soutiens')->latest('responded_at')->limit(3)->get();
    }

    public function soutenir(int $id): void
    {
        if (! auth()->check()) {
            $this->redirectRoute('login');

            return;
        }

        $record = Idea::findOrFail($id);
        $this->authorize('soutenir', $record);
        $this->throttlePerUser('soutien', maxAttempts: 30, decaySeconds: 60);

        $record->ajouterSoutien(auth()->user());
        unset($this->items);

        Flux::toast(variant: 'success', text: 'Votre soutien a bien été pris en compte.');
    }

    public function retirerSoutien(int $id): void
    {
        if (! auth()->check()) {
            $this->redirectRoute('login');

            return;
        }

        $record = Idea::findOrFail($id);
        $this->authorize('retirerSoutien', $record);
        $this->throttlePerUser('soutien', maxAttempts: 30, decaySeconds: 60);

        $record->retirerSoutien(auth()->user());
        unset($this->items);

        Flux::toast(text: 'Votre soutien a été retiré.');
    }
}; ?>

@php
    $filtre = $filterCategory !== '' || $filterStatus !== '';
@endphp

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        label="Participation"
        title="Boîte à idées de la colonie"
        subtitle="Proposez vos idées pour améliorer Nova Terra et soutenez celles des autres habitants. La ville répond à chacune."
    >
        <x-slot:meta>
            <div class="mt-4 flex flex-wrap gap-3">
                @auth
                    <flux:button variant="primary" icon="light-bulb" :href="route('ideas.create')" wire:navigate>Proposer une idée</flux:button>
                    <flux:button icon="list-bullet" :href="route('ideas.mine')" wire:navigate>Mes idées</flux:button>
                @else
                    <flux:button variant="primary" icon="arrow-right-end-on-rectangle" :href="route('login')">Se connecter pour proposer ou soutenir</flux:button>
                @endauth
            </div>
        </x-slot:meta>
    </x-tn.page-header>

    @if ($this->retenues->isNotEmpty() && ! $filtre)
        <x-tn.panel label="Idées retenues par la ville">
            <ul class="grid gap-3 md:grid-cols-3">
                @foreach ($this->retenues as $retenue)
                    <li wire:key="retenue-{{ $retenue->id }}">
                        <a href="{{ route('ideas.show', $retenue) }}" wire:navigate class="block rounded-md border border-line p-3 hover:border-cyan">
                            <span class="block font-medium text-ink">{{ $retenue->title }}</span>
                            <span class="mt-1 block text-sm text-ink-2">{{ Idea::libelleCategorie($retenue->category) }} · {{ $retenue->soutiens_count }} soutien(s)</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-tn.panel>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <flux:select wire:model.live="tri" aria-label="Trier les idées" class="sm:max-w-52">
            @foreach ($this::TRI_OPTIONS as $valeur => $libelle)
                <flux:select.option :value="$valeur">{{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterCategory" aria-label="Filtrer par catégorie" class="sm:max-w-60">
            <flux:select.option value="">Catégorie : toutes</flux:select.option>
            @foreach (Idea::CATEGORY_LABELS as $valeur => $libelle)
                <flux:select.option :value="$valeur">{{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterStatus" aria-label="Filtrer par état" class="sm:max-w-52">
            <flux:select.option value="">État : tous</flux:select.option>
            @foreach (Idea::STATUS_LABELS as $valeur => $libelle)
                <flux:select.option :value="$valeur">{{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">Mise à jour…</span>
    </div>

    @error('throttle')
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" />
    @enderror

    @if ($this->items->isEmpty())
        <x-tn.empty icon="light-bulb" title="Aucune idée à afficher" :text="$filtre ? 'Aucune idée ne correspond aux filtres choisis.' : 'Soyez le premier à proposer une idée pour la colonie !'" />
    @else
        <ul class="space-y-3">
            @foreach ($this->items as $item)
                @php($estAMoi = auth()->check() && $item->user_id === auth()->id())
                <li wire:key="idee-{{ $item->id }}">
                    <x-tn.surface>
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-tn.status-badge :etat="$item->etatStatut()">{{ Idea::libelleStatut($item->status) }}</x-tn.status-badge>
                                    <span class="text-sm text-ink-2">{{ Idea::libelleCategorie($item->category) }}</span>
                                    @if ($estAMoi)
                                        <x-tn.status-badge etat="info">Votre idée</x-tn.status-badge>
                                    @endif
                                </div>
                                <h3 class="font-medium text-ink">
                                    <a href="{{ route('ideas.show', $item) }}" wire:navigate class="hover:text-cyan">{{ $item->title }}</a>
                                </h3>
                                <p class="text-sm text-ink-2">{{ Str::limit($item->description, 200) }}</p>
                                <p class="font-mono text-xs text-ink-2">Proposée le {{ Idea::dateLocale($item->created_at, 'd/m/Y') }}</p>
                            </div>

                            <div class="flex flex-col gap-2 md:shrink-0 md:items-end">
                                <span class="inline-flex items-center gap-1 text-sm text-ink" aria-live="polite">
                                    <flux:icon.users variant="micro" />
                                    <span><strong>{{ $item->soutiens_count }}</strong> {{ $item->soutiens_count > 1 ? 'soutiens' : 'soutien' }}</span>
                                </span>
                                @if ($item->soutenu_par_moi)
                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-cyan/40 bg-cyan/10 px-3 py-1.5 text-sm font-medium text-cyan">
                                        <flux:icon.check-circle variant="mini" />
                                        Vous soutenez cette idée
                                    </span>
                                    <flux:button size="xs" variant="ghost" wire:click="retirerSoutien({{ $item->id }})" wire:confirm="Retirer votre soutien à cette idée ?" wire:loading.attr="disabled">
                                        Retirer mon soutien
                                    </flux:button>
                                @elseif ($estAMoi)
                                    <flux:button size="sm" variant="outline" icon="eye" :href="route('ideas.show', $item)" wire:navigate>Voir mon idée</flux:button>
                                @elseif (auth()->guest() && $item->estOuverte())
                                    <flux:button size="sm" variant="outline" icon="hand-thumb-up" :href="route('login')">Je soutiens</flux:button>
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
    @endif
</section>
