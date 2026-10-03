<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::agent'), Title('Comptes citoyens')] class extends Component {
    use WithPagination;

    public const STATUT_OPTIONS = ['actif' => 'Actifs', 'desactive' => 'Désactivés'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statut = '';

    /** Admin uniquement : citoyens ou agents (un agent ne voit que les citoyens). */
    #[Url(except: '')]
    public string $profil = '';

    public function mount(): void
    {
        $this->authorize('administerAccounts', User::class);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->authorize('administerAccounts', User::class);

        $this->reset('search', 'statut', 'profil');
        $this->resetPage();
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $this->authorize('administerAccounts', User::class);

        $actor = auth()->user();

        return User::query()
            ->manageableBy($actor)
            ->search($this->search)
            ->when($this->statut === 'actif', fn ($q) => $q->whereNull('deactivated_at'))
            ->when($this->statut === 'desactive', fn ($q) => $q->whereNotNull('deactivated_at'))
            ->when($actor->isAdmin() && in_array($this->profil, [Role::CITOYEN, Role::AGENT], true), fn ($q) => $q->where('role_id', Role::idFor($this->profil)))
            ->with('role')
            ->latest()
            ->paginate(15);
    }
}; ?>

<section class="w-full space-y-6">
    <x-tn.page-header
        label="Espace agent"
        title="Comptes citoyens"
        :subtitle="$this->items->total().' compte(s)'"
        :breadcrumb="['Espace agent' => route('agent.index'), 'Comptes citoyens' => null]"
    />

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" label="Rechercher" placeholder="Nom ou e-mail…" class="sm:max-w-xs" />

        <flux:select wire:model.live="statut" label="Statut" class="sm:max-w-44">
            <flux:select.option value="">Tous</flux:select.option>
            @foreach ($this::STATUT_OPTIONS as $valeur => $libelle)
                <flux:select.option value="{{ $valeur }}">{{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>

        @if (auth()->user()->isAdmin())
            <flux:select wire:model.live="profil" label="Profil" class="sm:max-w-44">
                <flux:select.option value="">Citoyens et agents</flux:select.option>
                <flux:select.option value="citoyen">Citoyens</flux:select.option>
                <flux:select.option value="agent">Agents</flux:select.option>
            </flux:select>
        @endif

        @if ($search !== '' || $statut !== '' || $profil !== '')
            <flux:button variant="ghost" icon="x-mark" wire:click="resetFilters">Effacer les filtres</flux:button>
        @endif

        <div wire:loading class="pb-2">
            <flux:icon.loading class="size-5" />
        </div>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="users" title="Aucun compte trouvé" :text="$search !== '' || $statut !== '' || $profil !== '' ? 'Aucun compte ne correspond à votre recherche.' : 'Aucun compte citoyen pour le moment.'" />
    @else
        <div class="overflow-x-auto">
            <flux:table :paginate="$this->items">
                <flux:table.columns>
                    <flux:table.column>Nom</flux:table.column>
                    <flux:table.column>E-mail</flux:table.column>
                    @if (auth()->user()->isAdmin())
                        <flux:table.column>Profil</flux:table.column>
                    @endif
                    <flux:table.column>Inscrit le</flux:table.column>
                    <flux:table.column>Statut</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->items as $item)
                        <flux:table.row wire:key="compte-{{ $item->id }}">
                            <flux:table.cell>
                                <flux:link :href="route('agent.citizens.show', $item)" wire:navigate class="font-medium">{{ $item->name }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $item->email }}</flux:table.cell>
                            @if (auth()->user()->isAdmin())
                                <flux:table.cell>{{ $item->role?->label }}</flux:table.cell>
                            @endif
                            <flux:table.cell class="whitespace-nowrap">{{ $item->created_at?->format('d/m/Y') }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($item->isActive())
                                    <x-tn.status-badge etat="normal">Actif</x-tn.status-badge>
                                @else
                                    <x-tn.status-badge etat="alerte">Désactivé</x-tn.status-badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end">
                                    <flux:button size="sm" variant="ghost" icon="eye" :href="route('agent.citizens.show', $item)" wire:navigate aria-label="Voir la fiche de {{ $item->name }}" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</section>
