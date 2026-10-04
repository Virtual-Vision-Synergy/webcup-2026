<?php

use App\Models\Remontee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Mes remontées')] class extends Component {
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', Remontee::class);
    }

    /**
     * Uniquement les remontées de l'utilisateur connecté (même pour un agent : le traitement est dans l'espace agent).
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return Remontee::query()
            ->whereBelongsTo(auth()->user())
            ->latest('envoyee_le')
            ->latest('id')
            ->paginate(10);
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        label="Vos données"
        title="Mes remontées"
        subtitle="Vos questions et inquiétudes sur l’usage de vos données, et où en est leur traitement."
        :breadcrumb="['Mon espace' => route('dashboard'), 'Mes remontées' => null]"
    >
        <x-slot:actions>
            <flux:button icon="shield-check" :href="route('privacy.show')" wire:navigate>Vos données</flux:button>
            <flux:button variant="primary" icon="plus" :href="route('concerns.create')" class="tn-cta" wire:navigate>
                Faire remonter une inquiétude
            </flux:button>
        </x-slot:actions>
    </x-tn.page-header>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="chat-bubble-left-ellipsis" title="Aucune remontée pour le moment" text="Une question sur vos données ? Écrivez-nous avec vos mots : vous recevrez un numéro de suivi.">
            <flux:button variant="primary" icon="plus" :href="route('concerns.create')" wire:navigate>Faire remonter une inquiétude</flux:button>
        </x-tn.empty>
    @else
        {{-- Mobile : liste --}}
        <ul class="md:hidden">
            @foreach ($this->items as $item)
                <li wire:key="m-{{ $item->id }}">
                    <x-tn.list-row icon="chat-bubble-left-ellipsis" :href="route('concerns.show', $item)" :stack="true">
                        <span class="block truncate font-medium text-ink">{{ $item->objet }}</span>
                        <span class="block truncate text-sm text-ink-2"><span class="font-mono text-xs">{{ $item->reference }}</span> · envoyée le {{ Remontee::dateLocale($item->envoyee_le, 'd/m/Y') }}</span>
                        <x-slot:aside>
                            <x-tn.status-badge :etat="$item->etatStatut()">{{ Remontee::libelleStatut($item->statut) }}</x-tn.status-badge>
                        </x-slot:aside>
                    </x-tn.list-row>
                </li>
            @endforeach
        </ul>
        <div class="md:hidden">{{ $this->items->links() }}</div>

        {{-- Desktop : tableau --}}
        <x-tn.surface padding="px-4 py-2" class="max-md:hidden">
            <flux:table :paginate="$this->items">
                <flux:table.columns>
                    <flux:table.column>Numéro de suivi</flux:table.column>
                    <flux:table.column>Objet</flux:table.column>
                    <flux:table.column>État</flux:table.column>
                    <flux:table.column>Envoyée le</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->items as $item)
                        <flux:table.row wire:key="row-{{ $item->id }}">
                            <flux:table.cell class="font-mono text-xs">{{ $item->reference }}</flux:table.cell>
                            <flux:table.cell><a href="{{ route('concerns.show', $item) }}" wire:navigate class="font-medium text-ink hover:text-cyan">{{ $item->objet }}</a></flux:table.cell>
                            <flux:table.cell><x-tn.status-badge :etat="$item->etatStatut()">{{ Remontee::libelleStatut($item->statut) }}</x-tn.status-badge></flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ Remontee::dateLocale($item->envoyee_le) }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-tn.surface>
    @endif
</section>
