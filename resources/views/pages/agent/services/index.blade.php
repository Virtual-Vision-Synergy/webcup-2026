<?php

use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Espace agent (F38) : disponibilité des services municipaux (maintenance, incident).
 */
new #[Layout('layouts::agent'), Title('Espace agent — Disponibilité des services')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $indisponibles = false;

    public function mount(): void
    {
        $this->authorize('manageAvailability', Service::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedIndisponibles(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $this->authorize('manageAvailability', Service::class);

        return Service::query()
            ->with('interruptionCourante')
            ->when(trim($this->search) !== '', fn ($query) => $query->where('nom', 'like', '%'.trim($this->search).'%'))
            ->when($this->indisponibles, fn ($query) => $query->whereHas('interruptionCourante'))
            ->orderBy('nom')
            ->paginate(15);
    }
}; ?>

<section class="w-full space-y-6">
    <x-tn.page-header
        label="Espace agent"
        title="Disponibilité des services"
        subtitle="Signalez une maintenance ou un incident : les habitants sont prévenus sur le catalogue et la fiche avant de commencer une démarche."
        :breadcrumb="['Espace agent' => route('agent.index'), 'Services' => null]"
    />

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" label="Rechercher" placeholder="Nom du service…" class="sm:max-w-xs" />
        <flux:checkbox wire:model.live="indisponibles" label="Indisponibles seulement" />
        <div wire:loading class="pb-2">
            <flux:icon.loading class="size-5" />
        </div>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="landmark" title="Aucun service" :text="$search !== '' || $indisponibles ? 'Aucun service ne correspond à ces critères.' : 'Aucun service n’est encore publié.'" />
    @else
        <div class="overflow-x-auto">
            <flux:table :paginate="$this->items">
                <flux:table.columns>
                    <flux:table.column>Service</flux:table.column>
                    <flux:table.column>Statut</flux:table.column>
                    <flux:table.column>Retour prévu</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->items as $item)
                        @php($interruption = $item->interruptionEnCours())
                        <flux:table.row wire:key="service-{{ $item->id }}">
                            <flux:table.cell>
                                <flux:link :href="route('services.show', $item)" wire:navigate class="font-medium">{{ $item->nom }}</flux:link>
                                @if ($interruption)
                                    <p class="max-w-xs truncate text-sm text-ink-2">{{ $interruption->motif }}</p>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($interruption)
                                    <x-tn.status-badge :etat="$interruption->etatBadge()">Indisponible · {{ $interruption->libelleType() }}</x-tn.status-badge>
                                @else
                                    <x-tn.status-badge etat="normal">Disponible</x-tn.status-badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap text-sm">
                                @if ($interruption)
                                    {{ $interruption->retour_prevu_at ? \App\Models\ServiceInterruption::libelleDate($interruption->retour_prevu_at) : 'Inconnu' }}
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end">
                                    <flux:button size="sm" :variant="$interruption ? 'primary' : 'ghost'" icon="wrench-screwdriver" :href="route('agent.services.availability', $item)" wire:navigate>
                                        {{ $interruption ? 'Mettre à jour' : 'Signaler une interruption' }}
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</section>
