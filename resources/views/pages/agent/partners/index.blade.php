<?php

use App\Models\Partner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Espace agent (F74) : liste de gestion des partenaires, brouillons compris (PartnerPolicy::manage).
 */
new #[Layout('layouts::agent'), Title('Espace agent — Partenaires')] class extends Component {
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('manage', Partner::class);
    }

    /**
     * @return LengthAwarePaginator<int, Partner>
     */
    #[Computed]
    public function partners(): LengthAwarePaginator
    {
        $this->authorize('manage', Partner::class);

        return Partner::query()->orderBy('name')->paginate(15);
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('Partenaires') }}</flux:heading>
            <flux:text>{{ __('Horaires, adresse et emplacement affichés sur la page publique.') }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button icon="eye" :href="route('partners.index')">{{ __('Voir la page publique') }}</flux:button>
            <flux:button variant="primary" icon="plus" :href="route('agent.partners.create')">{{ __('Ajouter un partenaire') }}</flux:button>
        </div>
    </div>

    @if ($this->partners->isEmpty())
        <flux:card class="text-center">
            <flux:heading>{{ __('Aucun partenaire pour le moment.') }}</flux:heading>
            <flux:button class="mt-3" variant="primary" :href="route('agent.partners.create')">{{ __('Ajouter le premier partenaire') }}</flux:button>
        </flux:card>
    @else
        <flux:table :paginate="$this->partners">
            <flux:table.columns>
                <flux:table.column>{{ __('Nom') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Statut') }}</flux:table.column>
                <flux:table.column>{{ __('Visibilité') }}</flux:table.column>
                <flux:table.column><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->partners as $partner)
                    @php($statut = $partner->openingStatus())
                    <flux:table.row :key="$partner->id">
                        <flux:table.cell class="font-medium">{{ $partner->name }}</flux:table.cell>
                        <flux:table.cell>{{ Partner::labelType($partner->type) }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$statut['color']" :icon="$statut['icon']">{{ $statut['label'] }}</flux:badge></flux:table.cell>
                        <flux:table.cell>
                            @if ($partner->is_published)
                                <flux:badge size="sm" color="green" icon="eye">{{ __('Publié') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc" icon="eye-slash">{{ __('Brouillon') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-end">
                            <flux:button size="sm" variant="ghost" :href="route('partners.show', $partner)">{{ __('Voir') }}</flux:button>
                            <flux:button size="sm" icon="pencil-square" :href="route('agent.partners.edit', $partner)">{{ __('Modifier') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
