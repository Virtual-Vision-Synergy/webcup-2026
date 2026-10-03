<?php

use App\Models\Partner;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Gestion des partenaires (F74), espace agent : publiés et brouillons, ajout, modification, suppression.
 */
new #[Layout('layouts::agent'), Title('Partenaires')] class extends Component {
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('manage', Partner::class);
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $this->authorize('manage', Partner::class);

        return Partner::query()->orderByDesc('is_published')->orderBy('name')->paginate(15);
    }

    public function delete(int $id): void
    {
        $partner = Partner::query()->findOrFail($id);
        $this->authorize('delete', $partner);

        $partner->delete();
        unset($this->items);

        Flux::toast(variant: 'success', text: __('Partenaire supprimé.'));
    }
}; ?>

<section class="w-full space-y-6">
    <x-tn.page-header :label="__('Espace agent')" :title="__('Partenaires')" :subtitle="__('Horaires, adresse et emplacement affichés sur la page publique « Partenaires ».')">
        <x-slot:actions>
            <flux:button :href="route('partners.index')" icon="eye">{{ __('Page publique') }}</flux:button>
            <flux:button variant="primary" icon="plus" :href="route('agent.partners.create')">{{ __('Ajouter un partenaire') }}</flux:button>
        </x-slot:actions>
    </x-tn.page-header>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="building-storefront" :title="__('Aucun partenaire')" :text="__('Ajoutez le premier partenaire : il apparaîtra sur la carte publique une fois publié.')">
            <flux:button variant="primary" icon="plus" :href="route('agent.partners.create')">{{ __('Ajouter un partenaire') }}</flux:button>
        </x-tn.empty>
    @else
        <flux:table :paginate="$this->items">
            <flux:table.columns>
                <flux:table.column>{{ __('Nom') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Publication') }}</flux:table.column>
                <flux:table.column>{{ __('Maintenant') }}</flux:table.column>
                <flux:table.column><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->items as $partner)
                    <flux:table.row :key="$partner->id">
                        <flux:table.cell>
                            <a href="{{ route('partners.show', $partner) }}" class="font-medium text-ink hover:text-cyan hover:underline">{{ $partner->name }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ __($partner->typeLabel()) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$partner->is_published ? 'green' : 'zinc'">{{ $partner->is_published ? __('Publié') : __('Brouillon') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">{{ __($partner->openingStatus()['label']) }}</flux:table.cell>
                        <flux:table.cell class="text-end">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('agent.partners.edit', $partner)">{{ __('Modifier') }}</flux:button>
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $partner->id }})" wire:confirm="{{ __('Supprimer ce partenaire ? Il disparaîtra de la page publique.') }}">
                                    <span class="sr-only">{{ __('Supprimer :nom', ['nom' => $partner->name]) }}</span>
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
