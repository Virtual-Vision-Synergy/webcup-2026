<?php

use App\Models\Demarche;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Mes demandes')] class extends Component {
    use WithPagination;

    /** Filtre par état : « » (toutes), « en_cours » ou « terminees ». */
    #[Url(except: '')]
    public string $etat = '';

    /** Statuts considérés comme terminés. */
    private const STATUTS_TERMINES = ['traitee', 'refusee'];

    public function mount(): void
    {
        $this->authorize('viewAny', Demarche::class);
    }

    public function updatedEtat(): void
    {
        if (! in_array($this->etat, ['', 'en_cours', 'terminees'], true)) {
            $this->etat = '';
        }

        $this->resetPage();
    }

    /**
     * Uniquement les demandes de l'utilisateur connecté, de la plus récente à la plus ancienne.
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return auth()->user()->demarches()
            ->with(['service', 'etapes'])
            ->when($this->etat === 'en_cours', fn ($query) => $query->whereNotIn('statut', self::STATUTS_TERMINES))
            ->when($this->etat === 'terminees', fn ($query) => $query->whereIn('statut', self::STATUTS_TERMINES))
            ->latest()
            ->latest('id')
            ->paginate(10);
    }
}; ?>

<section class="mx-auto w-full max-w-4xl space-y-6">
    <x-tn.page-header
        label="Mon espace"
        title="Mes demandes"
        :subtitle="$this->items->total().' demande(s) · état actuel et étapes réalisées'"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Démarches' => route('demarches.index'), 'Mes demandes' => null]"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('demarches.create')" class="tn-cta" wire:navigate>
                Nouvelle démarche
            </flux:button>
        </x-slot:actions>
    </x-tn.page-header>

    <div class="flex flex-wrap items-center gap-3">
        <flux:select wire:model.live="etat" aria-label="Filtrer par état" class="sm:max-w-52">
            <flux:select.option value="">État : toutes</flux:select.option>
            <flux:select.option value="en_cours">En cours</flux:select.option>
            <flux:select.option value="terminees">Terminées</flux:select.option>
        </flux:select>
        <span wire:loading class="font-mono text-[11px] uppercase tracking-[.06em] text-cyan">Mise à jour…</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="file-text" title="Aucune demande dans l'historique" text="Modifiez le filtre ou déposez votre première démarche.">
            <flux:button variant="primary" icon="plus" :href="route('demarches.create')" wire:navigate>Nouvelle démarche</flux:button>
        </x-tn.empty>
    @else
        <ul>
            @foreach ($this->items as $item)
                <li wire:key="h-{{ $item->id }}">
                    <x-tn.list-row icon="file-text" :href="route('demarches.show', $item)" :stack="true">
                        <span class="block truncate font-medium text-ink">{{ $item->titre }}</span>
                        <span class="block truncate text-sm text-ink-2">{{ $item->service?->nom ?? 'Service non précisé' }} · <span class="font-mono text-xs">{{ $item->created_at->format('d.m.Y') }}</span></span>
                        {{-- D11 : étapes déjà réalisées, datées --}}
                        <span class="mt-1 block text-xs text-ink-2" data-test="etapes">
                            <span class="sr-only">Étapes réalisées : </span>Déposée le <span class="font-mono">{{ $item->created_at->format('d.m') }}</span>@foreach ($item->etapes as $etape) → {{ Demarche::libelleStatut($etape->statut) }} le <span class="font-mono">{{ $etape->created_at?->format('d.m') }}</span>@endforeach
                        </span>
                        <x-slot:aside>
                            <x-tn.status-badge :etat="$item->etatStatut()">{{ Demarche::libelleStatut($item->statut) }}</x-tn.status-badge>
                        </x-slot:aside>
                    </x-tn.list-row>
                </li>
            @endforeach
        </ul>
        {{ $this->items->links() }}
    @endif
</section>
