<?php

use App\Models\Idea;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::agent'), Title('Espace agent — Boîte à idées')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $filterStatus = '';

    #[Url(except: '')]
    public string $filterCategory = '';

    public function mount(): void
    {
        Gate::authorize('viewAgentSpace');
        $this->authorize('manage', Idea::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['filterStatus', 'filterCategory'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->authorize('manage', Idea::class);

        $this->reset('filterStatus', 'filterCategory');
        $this->resetPage();
    }

    /**
     * Les plus soutenues d'abord (puis les plus anciennes) : la ville voit ce qui compte le plus pour les habitants.
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $this->authorize('manage', Idea::class);

        return Idea::query()
            ->withCount('soutiens')
            ->when(in_array($this->filterStatus, Idea::STATUS_OPTIONS, true), fn ($query) => $query->where('status', $this->filterStatus))
            ->when(in_array($this->filterCategory, Idea::CATEGORY_OPTIONS, true), fn ($query) => $query->where('category', $this->filterCategory))
            ->orderByDesc('soutiens_count')
            ->oldest()
            ->oldest('id')
            ->paginate(15);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function compteurs(): array
    {
        $this->authorize('manage', Idea::class);

        $parStatut = Idea::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(Idea::STATUS_OPTIONS)->mapWithKeys(fn (string $status): array => [$status => (int) ($parStatut[$status] ?? 0)])->all();
    }
}; ?>

@php
    $filtre = $filterStatus !== '' || $filterCategory !== '';
@endphp

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        label="Espace agent"
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Boîte à idées' => null]"
        title="Boîte à idées"
        :subtitle="($this->compteurs['recue'] + $this->compteurs['a_l_etude']).' idée(s) en attente d’une réponse sur '.array_sum($this->compteurs).' au total. Triées par nombre de soutiens.'"
    />

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach (Idea::STATUS_OPTIONS as $status)
            <button
                type="button"
                wire:click="$set('filterStatus', '{{ $filterStatus === $status ? '' : $status }}')"
                aria-pressed="{{ $filterStatus === $status ? 'true' : 'false' }}"
                @class(['rounded-md border p-4 text-start transition hover:border-cyan', 'border-cyan ring-2 ring-cyan' => $filterStatus === $status, 'border-line' => $filterStatus !== $status])
            >
                <x-tn.status-badge :etat="Idea::STATUS_ETATS[$status]">{{ Idea::libelleStatut($status) }}</x-tn.status-badge>
                <span class="tn-display mt-2 block text-2xl font-semibold text-ink">{{ $this->compteurs[$status] }}</span>
            </button>
        @endforeach
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <flux:select wire:model.live="filterStatus" aria-label="Filtrer par état" class="sm:max-w-52">
            <flux:select.option value="">État : tous</flux:select.option>
            @foreach (Idea::STATUS_LABELS as $valeur => $libelle)
                <flux:select.option :value="$valeur">{{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterCategory" aria-label="Filtrer par catégorie" class="sm:max-w-60">
            <flux:select.option value="">Catégorie : toutes</flux:select.option>
            @foreach (Idea::CATEGORY_LABELS as $valeur => $libelle)
                <flux:select.option :value="$valeur">{{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>
        @if ($filtre)
            <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="resetFilters">Effacer les filtres</flux:button>
        @endif
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">Mise à jour…</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="light-bulb" title="Aucune idée à afficher" :text="$filtre ? 'Aucune idée ne correspond aux filtres choisis.' : 'Les habitants n’ont encore proposé aucune idée.'" />
    @else
        <ul class="space-y-3">
            @foreach ($this->items as $item)
                <li wire:key="idee-agent-{{ $item->id }}" class="flex flex-col gap-2 rounded-md border border-line p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-tn.status-badge :etat="$item->etatStatut()">{{ Idea::libelleStatut($item->status) }}</x-tn.status-badge>
                            <span class="font-mono text-xs text-ink-2">{{ $item->reference }}</span>
                            @if ($item->estMasquee())
                                <x-tn.status-badge etat="alerte">Masquée</x-tn.status-badge>
                            @endif
                        </div>
                        <a href="{{ route('agent.ideas.show', $item) }}" class="block font-medium text-ink hover:text-cyan">{{ $item->title }}</a>
                        <p class="text-sm text-ink-2">
                            {{ Idea::libelleCategorie($item->category) }} ·
                            <span class="font-mono text-xs">Proposée le {{ Idea::dateLocale($item->created_at, 'd/m/Y') }}</span>
                        </p>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1 text-sm text-ink" title="Soutiens d'habitants">
                        <flux:icon.users variant="micro" />
                        <strong>{{ $item->soutiens_count }}</strong> {{ $item->soutiens_count > 1 ? 'soutiens' : 'soutien' }}
                    </span>
                </li>
            @endforeach
        </ul>

        {{ $this->items->links() }}
    @endif
</section>
