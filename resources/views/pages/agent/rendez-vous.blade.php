<?php

use App\Models\ActionLog;
use App\Models\CreneauRendezVous;
use App\Models\RendezVous;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Espace agent (F39) : rendez-vous d'une journée (aujourd'hui par défaut, heure de Nova Terra), triés par heure.
 */
new #[Layout('layouts::agent'), Title('Espace agent — Rendez-vous du jour')] class extends Component {
    #[Url(except: '')]
    public string $date = '';

    #[Url(except: '')]
    public string $filterServiceId = '';

    #[Url(except: false)]
    public bool $avecAnnules = false;

    public function mount(): void
    {
        $this->authorize('viewAgenda', RendezVous::class);
    }

    public function aujourdhui(): void
    {
        $this->authorize('viewAgenda', RendezVous::class);

        $this->date = '';
    }

    /**
     * Jour affiché, en heure locale. Une date invalide dans l'URL retombe sur aujourd'hui.
     */
    #[Computed]
    public function jour(): CarbonImmutable
    {
        $fuseau = CreneauRendezVous::fuseau();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) === 1) {
            $jour = CarbonImmutable::createFromFormat('!Y-m-d', $this->date, $fuseau);

            if ($jour !== null && $jour->format('Y-m-d') === $this->date) {
                return $jour;
            }
        }

        return CarbonImmutable::now($fuseau)->startOfDay();
    }

    #[Computed]
    public function estAujourdhui(): bool
    {
        return $this->jour->isSameDay(CarbonImmutable::now(CreneauRendezVous::fuseau()));
    }

    /**
     * @return Collection<int, RendezVous>
     */
    #[Computed]
    public function items(): Collection
    {
        $this->authorize('viewAgenda', RendezVous::class);

        $debut = $this->jour->utc();

        return RendezVous::query()
            ->entre($debut, $debut->addDay())
            ->when(! $this->avecAnnules, fn ($query) => $query->where('statut', '!=', 'annule'))
            ->when(ctype_digit($this->filterServiceId), fn ($query) => $query->where('service_id', (int) $this->filterServiceId))
            ->with(['user:id,name', 'service:id,nom', 'creneau'])
            ->orderBy(
                CreneauRendezVous::select('debut')->whereColumn('creneaux_rendez_vous.id', 'rendez_vous.creneau_id'),
            )
            ->get();
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function serviceOptions(): Collection
    {
        return Service::query()->prendRendezVous()->orderBy('nom')->get(['id', 'nom']);
    }

    /**
     * Bonus : l'agent marque le rendez-vous honoré ou absent.
     */
    public function changerStatut(int $id, string $statut): void
    {
        $rendezVous = RendezVous::findOrFail($id);
        $this->authorize('changerStatut', $rendezVous);
        abort_unless(in_array($statut, RendezVous::STATUTS_AGENT, true), 422);

        $rendezVous->changerStatut($statut);
        ActionLog::record('rendez_vous_statut', $rendezVous);
        unset($this->items);

        Flux::toast(variant: 'success', text: 'Rendez-vous marqué « '.RendezVous::libelleStatut($statut).' ».');
    }
}; ?>

<section class="w-full space-y-6">
    <x-tn.page-header
        label="Espace agent"
        :title="$this->estAujourdhui ? 'Rendez-vous du jour' : 'Rendez-vous'"
        :subtitle="Str::ucfirst($this->jour->locale('fr')->translatedFormat('l j F Y')).' — '.$this->items->count().' rendez-vous (heure de Nova Terra)'"
    />

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        <flux:input type="date" wire:model.live="date" label="Jour" class="sm:max-w-48" />
        <flux:select wire:model.live="filterServiceId" label="Service" class="sm:max-w-64">
            <flux:select.option value="">Tous les services</flux:select.option>
            @foreach ($this->serviceOptions as $option)
                <flux:select.option :value="$option->id">{{ $option->nom }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:checkbox wire:model.live="avecAnnules" label="Afficher les annulés" class="sm:mb-2" />
        @unless ($this->estAujourdhui)
            <flux:button wire:click="aujourdhui" icon="calendar" class="sm:mb-0.5">Aujourd'hui</flux:button>
        @endunless
        <span wire:loading class="font-mono text-[11px] uppercase tracking-[.06em] text-cyan sm:mb-2">Mise à jour…</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="calendar-days" title="Aucun rendez-vous ce jour-là" text="Changez de jour ou de service." />
    @else
        <x-tn.surface padding="px-4 py-2">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Heure</flux:table.column>
                    <flux:table.column>Service</flux:table.column>
                    <flux:table.column>Habitant</flux:table.column>
                    <flux:table.column>Motif</flux:table.column>
                    <flux:table.column>Statut</flux:table.column>
                    <flux:table.column><span class="sr-only">Actions</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->items as $item)
                        <flux:table.row wire:key="rdv-{{ $item->id }}">
                            <flux:table.cell class="whitespace-nowrap font-mono text-sm">{{ $item->creneau->libelleHeureDebut() }} – {{ $item->creneau->libelleHeureFin() }}</flux:table.cell>
                            <flux:table.cell>{{ $item->service->nom }}</flux:table.cell>
                            <flux:table.cell>{{ $item->user->name }}</flux:table.cell>
                            <flux:table.cell class="max-w-xs whitespace-normal text-sm">{{ $item->motif ?? '—' }}</flux:table.cell>
                            <flux:table.cell><x-tn.status-badge :etat="$item->etatStatut()">{{ RendezVous::libelleStatut($item->statut) }}</x-tn.status-badge></flux:table.cell>
                            <flux:table.cell>
                                @can('changerStatut', $item)
                                    <div class="flex justify-end gap-1">
                                        <flux:button size="sm" wire:click="changerStatut({{ $item->id }}, 'honore')">Honoré</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="changerStatut({{ $item->id }}, 'absent')" wire:confirm="Marquer {{ $item->user->name }} absent(e) ?">Absent</flux:button>
                                    </div>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-tn.surface>
    @endif
</section>
