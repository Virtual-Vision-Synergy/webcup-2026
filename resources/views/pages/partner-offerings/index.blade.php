<?php

use App\Models\Partner;
use App\Models\PartnerOffering;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Espace partenaire (F99) : un compte partenaire voit et gère uniquement les services de SON partenaire ;
 * l'admin voit ceux de tous les partenaires (filtre par partenaire). PartnerOfferingPolicy dans chaque action.
 */
new #[Title('Espace partenaire — Mes services')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $filterStatus = '';

    /** Admin uniquement (ignoré pour un compte partenaire, limité à son partenaire par gerablesPar). */
    #[Url(except: '')]
    public string $filterPartnerId = '';

    /** @var array<int|string, string> Date « Indisponible jusqu'au » saisie sur chaque ligne (Y-m-d). */
    public array $jusquAu = [];

    public function mount(): void
    {
        $this->authorize('viewAny', PartnerOffering::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPartnerId(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return PartnerOffering::query()
            ->gerablesPar(auth()->user())
            ->with('partner')
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
            ->when(in_array($this->filterStatus, PartnerOffering::STATUS_OPTIONS, true), fn ($query) => $query->where('status', $this->filterStatus))
            ->when(auth()->user()->isAdmin() && ctype_digit($this->filterPartnerId), fn ($query) => $query->where('partner_id', (int) $this->filterPartnerId))
            ->orderBy('title')
            ->paginate(10);
    }

    /**
     * Admin : partenaires proposés dans le filtre.
     *
     * @return Collection<int, Partner>
     */
    #[Computed]
    public function partnerOptions(): Collection
    {
        return auth()->user()->isAdmin()
            ? Partner::query()->orderBy('name')->get(['id', 'name'])
            : new Collection;
    }

    /**
     * Changement d'état rapide, en un clic depuis la liste.
     */
    public function changeStatus(int $id, string $status): void
    {
        $record = PartnerOffering::findOrFail($id);
        $this->authorize('changeStatus', $record);

        $this->resetErrorBag();

        validator(
            ['status' => $status, 'jusquAu' => (string) ($this->jusquAu[$id] ?? '')],
            [
                'status' => ['required', Rule::in(PartnerOffering::STATUS_OPTIONS)],
                'jusquAu' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            ],
            [],
            ['jusquAu' => 'date de retour'],
        )->validate();

        $record->status = $status;
        $record->unavailable_until = $status === PartnerOffering::STATUS_UNAVAILABLE && filled($this->jusquAu[$id] ?? null)
            ? Carbon::createFromFormat('Y-m-d', (string) $this->jusquAu[$id])->startOfDay()
            : null;
        $record->save();

        Flux::toast(variant: 'success', text: __('État mis à jour : :etat.', ['etat' => $record->libelleEtatDetaille()]));
    }

    public function delete(int $id): void
    {
        $record = PartnerOffering::findOrFail($id);
        $this->authorize('delete', $record);

        $record->delete();

        Flux::toast(variant: 'success', text: __('Service partenaire supprimé.'));
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        :label="__('Espace partenaire')"
        :title="auth()->user()->isAdmin() ? __('Services des partenaires') : __('Mes services')"
        :subtitle="auth()->user()->isAdmin() ? __('Tous les partenaires (administration).') : (auth()->user()->partner?->name ?? '')"
        :breadcrumb="[__('Mon espace') => route('dashboard'), __('Espace partenaire') => null]"
    >
        <x-slot:actions>
            @can('create', PartnerOffering::class)
                <flux:button variant="primary" icon="plus" :href="route('partner.offerings.create')" wire:navigate>{{ __('Ajouter un service') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Rechercher un service…')" :aria-label="__('Rechercher un service')" clearable class="sm:max-w-sm" />
        <flux:select wire:model.live="filterStatus" :aria-label="__('Filtrer par état')" class="sm:max-w-48">
            <flux:select.option value="">{{ __('Tous les états') }}</flux:select.option>
            @foreach (PartnerOffering::STATUS_LABELS as $valeur => $libelle)
                <flux:select.option value="{{ $valeur }}">{{ __($libelle) }}</flux:select.option>
            @endforeach
        </flux:select>
        @if (auth()->user()->isAdmin())
            <flux:select wire:model.live="filterPartnerId" :aria-label="__('Filtrer par partenaire')" class="sm:max-w-64">
                <flux:select.option value="">{{ __('Tous les partenaires') }}</flux:select.option>
                @foreach ($this->partnerOptions as $partner)
                    <flux:select.option value="{{ $partner->id }}">{{ $partner->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">{{ __('Mise à jour…') }}</span>
    </div>

    @error('status') <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" /> @enderror
    @error('jusquAu') <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" /> @enderror

    @if ($this->items->isEmpty())
        <x-tn.empty icon="building-storefront" :title="__('Aucun service pour le moment')" :text="__('Ajoutez le premier service proposé aux habitants : il apparaîtra dans le catalogue une fois publié.')">
            <flux:button variant="primary" icon="plus" :href="route('partner.offerings.create')" wire:navigate>{{ __('Ajouter un service') }}</flux:button>
        </x-tn.empty>
    @else
        <ul class="space-y-3">
            @foreach ($this->items as $item)
                <li wire:key="offre-{{ $item->id }}" class="space-y-3 rounded-md border border-line bg-surface p-4 md:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 space-y-1">
                            <h2 class="font-semibold text-ink">{{ $item->title }}</h2>
                            <div class="flex flex-wrap gap-1.5">
                                <x-service-status :service="$item" compact />
                                @if (auth()->user()->isAdmin())
                                    <flux:badge size="sm" icon="building-storefront">{{ $item->partner->name }}</flux:badge>
                                @endif
                                @unless ($item->is_published)
                                    <flux:badge size="sm" color="amber" icon="eye-slash">{{ __('Brouillon') }}</flux:badge>
                                @endunless
                            </div>
                        </div>
                        <div class="flex gap-1">
                            @if ($item->is_published && $item->partner->is_published)
                                <flux:button size="sm" variant="ghost" icon="eye" :href="route('catalogue.partners.show', $item)" :aria-label="__('Voir la fiche publique de :titre', ['titre' => $item->title])" />
                            @endif
                            @can('update', $item)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('partner.offerings.edit', $item)" wire:navigate :aria-label="__('Modifier :titre', ['titre' => $item->title])" />
                            @endcan
                            @can('delete', $item)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="{{ __('Supprimer ce service ?') }}" :aria-label="__('Supprimer :titre', ['titre' => $item->title])" />
                            @endcan
                        </div>
                    </div>

                    @can('changeStatus', $item)
                        <div class="flex flex-wrap items-end gap-2 border-t border-line pt-3" role="group" aria-label="{{ __('Changer l\'état de :titre', ['titre' => $item->title]) }}">
                            <flux:button size="sm" icon="check-circle" :variant="$item->status === PartnerOffering::STATUS_AVAILABLE ? 'filled' : 'ghost'" wire:click="changeStatus({{ $item->id }}, 'available')" :aria-pressed="$item->status === PartnerOffering::STATUS_AVAILABLE ? 'true' : 'false'">{{ __('Disponible') }}</flux:button>
                            <flux:button size="sm" icon="user-group" :variant="$item->status === PartnerOffering::STATUS_FULL ? 'filled' : 'ghost'" wire:click="changeStatus({{ $item->id }}, 'full')" :aria-pressed="$item->status === PartnerOffering::STATUS_FULL ? 'true' : 'false'">{{ __('Complet') }}</flux:button>
                            <div class="flex items-end gap-1">
                                <flux:input type="date" size="sm" wire:model="jusquAu.{{ $item->id }}" :aria-label="__('Indisponible jusqu\'au (facultatif)')" class="max-w-40" />
                                <flux:button size="sm" icon="x-circle" :variant="$item->status === PartnerOffering::STATUS_UNAVAILABLE ? 'filled' : 'ghost'" wire:click="changeStatus({{ $item->id }}, 'unavailable')" :aria-pressed="$item->status === PartnerOffering::STATUS_UNAVAILABLE ? 'true' : 'false'">{{ __('Indisponible') }}</flux:button>
                            </div>
                            <span wire:loading wire:target="changeStatus({{ $item->id }})" class="text-xs text-cyan">{{ __('Enregistrement…') }}</span>
                        </div>
                    @endcan
                </li>
            @endforeach
        </ul>

        <div>{{ $this->items->links() }}</div>
    @endif
</section>
