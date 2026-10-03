<?php

use App\Models\CreneauRendezVous;
use App\Models\RendezVous;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Mes rendez-vous » (F39) : toujours limités à l'utilisateur connecté.
 */
new #[Title('Mes rendez-vous')] class extends Component {
    use WithPagination;

    /** « a-venir » ou « passes ». */
    #[Url(except: 'a-venir')]
    public string $onglet = 'a-venir';

    public function mount(): void
    {
        $this->authorize('viewAny', RendezVous::class);

        if (! in_array($this->onglet, ['a-venir', 'passes'], true)) {
            $this->onglet = 'a-venir';
        }
    }

    public function choisirOnglet(string $onglet): void
    {
        $this->authorize('viewAny', RendezVous::class);

        $this->onglet = $onglet === 'passes' ? 'passes' : 'a-venir';
        $this->resetPage();
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $aVenir = $this->onglet === 'a-venir';

        return RendezVous::query()
            ->whereBelongsTo(auth()->user())
            ->whereHas('creneau', fn (Builder $q) => $q->where('debut', $aVenir ? '>=' : '<', now()))
            ->with(['service:id,nom,slug,lieu_rendez_vous,adresse', 'creneau'])
            ->orderBy(
                CreneauRendezVous::select('debut')->whereColumn('creneaux_rendez_vous.id', 'rendez_vous.creneau_id'),
                $aVenir ? 'asc' : 'desc',
            )
            ->paginate(10);
    }
}; ?>

<section class="mx-auto w-full max-w-4xl space-y-6">
    <x-tn.page-header
        label="Rendez-vous"
        title="Mes rendez-vous"
        subtitle="Vos rendez-vous avec les agents de la mairie, en heure de Nova Terra."
        :breadcrumb="['Mon espace' => route('dashboard'), 'Rendez-vous' => null]"
    >
        <x-slot:actions>
            @can('create', RendezVous::class)
                <flux:button variant="primary" icon="plus" :href="route('appointments.create')" class="tn-cta" wire:navigate>
                    Prendre rendez-vous
                </flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <div class="flex gap-2" role="tablist" aria-label="Période">
        <flux:button size="sm" wire:click="choisirOnglet('a-venir')" :variant="$onglet === 'a-venir' ? 'primary' : 'ghost'" role="tab" :aria-selected="$onglet === 'a-venir' ? 'true' : 'false'">À venir</flux:button>
        <flux:button size="sm" wire:click="choisirOnglet('passes')" :variant="$onglet === 'passes' ? 'primary' : 'ghost'" role="tab" :aria-selected="$onglet === 'passes' ? 'true' : 'false'">Passés</flux:button>
        <span wire:loading class="self-center font-mono text-[11px] uppercase tracking-[.06em] text-cyan">Mise à jour…</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty
            icon="calendar-days"
            :title="$onglet === 'a-venir' ? 'Aucun rendez-vous à venir' : 'Aucun rendez-vous passé'"
            text="Prenez rendez-vous avec un agent : état civil, urbanisme, action sociale…"
        >
            <flux:button variant="primary" icon="plus" :href="route('appointments.create')" wire:navigate>Prendre rendez-vous</flux:button>
        </x-tn.empty>
    @else
        <ul>
            @foreach ($this->items as $item)
                <li wire:key="rdv-{{ $item->id }}">
                    <x-tn.list-row icon="calendar-days" :href="route('appointments.show', $item)" :stack="true">
                        <span class="block font-medium text-ink">{{ $item->service->nom }}</span>
                        <span class="block text-sm text-ink-2">{{ $item->creneau->libelleComplet() }}</span>
                        <span class="block truncate text-sm text-ink-2">{{ $item->service->lieuRendezVous() }}</span>
                        <x-slot:aside>
                            <x-tn.status-badge :etat="$item->etatStatut()">{{ RendezVous::libelleStatut($item->statut) }}</x-tn.status-badge>
                        </x-slot:aside>
                    </x-tn.list-row>
                </li>
            @endforeach
        </ul>
        {{ $this->items->links() }}
    @endif
</section>
