<?php

use App\Models\Projet;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Espace agent (F67) : création, mise à jour et suppression des projets de la ville (ProjetPolicy).
 */
new #[Layout('layouts::agent'), Title('Projets de la ville')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('create', Projet::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return Projet::query()
            ->with('quartier:id,nom')
            ->when(trim($this->search) !== '', fn ($query) => $query->where('titre', 'like', '%'.trim($this->search).'%'))
            ->latest('updated_at')
            ->paginate(15);
    }

    public function delete(int $id): void
    {
        $record = Projet::findOrFail($id);
        $this->authorize('delete', $record);

        $record->delete();

        Flux::toast(variant: 'success', text: 'Projet supprimé.');
    }
}; ?>

<section class="space-y-6">
    <x-tn.page-header
        label="Espace agent"
        title="Projets de la ville"
        subtitle="Publiez les projets et tenez à jour leur avancement : les habitants les consultent sur la page publique."
    >
        <x-slot:actions>
            <flux:button icon="eye" variant="ghost" :href="route('projets.index')">Voir la page publique</flux:button>
            <flux:button variant="primary" icon="plus" :href="route('agent.projets.create')" wire:navigate>Nouveau projet</flux:button>
        </x-slot:actions>
    </x-tn.page-header>

    <div class="flex items-center gap-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Rechercher un projet…" aria-label="Rechercher un projet" clearable class="sm:max-w-sm" />
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">Mise à jour…</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="building-office-2" title="Aucun projet" text="{{ trim($search) !== '' ? 'Aucun projet ne correspond à cette recherche.' : 'Publiez le premier projet de la ville.' }}">
            <flux:button variant="primary" icon="plus" :href="route('agent.projets.create')" wire:navigate>Nouveau projet</flux:button>
        </x-tn.empty>
    @else
        <div class="overflow-x-auto rounded-md border border-line bg-surface">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Projet</flux:table.column>
                    <flux:table.column>Quartier</flux:table.column>
                    <flux:table.column>État</flux:table.column>
                    <flux:table.column>Avancement</flux:table.column>
                    <flux:table.column>Mis à jour</flux:table.column>
                    <flux:table.column><span class="sr-only">Actions</span></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->items as $item)
                        <flux:table.row wire:key="projet-{{ $item->id }}">
                            <flux:table.cell class="max-w-72 truncate font-medium">
                                <a href="{{ route('projets.show', $item) }}" class="hover:text-cyan hover:underline">{{ $item->titre }}</a>
                            </flux:table.cell>
                            <flux:table.cell>{{ $item->quartier?->nom ?? 'Toute la ville' }}</flux:table.cell>
                            <flux:table.cell>
                                <x-tn.status-badge :etat="$item->badgeEtat()">{{ Projet::libelleEtat($item->etat) }}</x-tn.status-badge>
                            </flux:table.cell>
                            <flux:table.cell class="font-mono">{{ $item->avancement() }} %</flux:table.cell>
                            <flux:table.cell>{{ $item->updated_at?->diffForHumans() }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('agent.projets.edit', $item)" wire:navigate aria-label="Mettre à jour {{ $item->titre }}" />
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="Supprimer définitivement ce projet ?" aria-label="Supprimer {{ $item->titre }}" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <div>{{ $this->items->links() }}</div>
    @endif
</section>
