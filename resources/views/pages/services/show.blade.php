<?php

use App\Models\Service;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Service')] class extends Component {
    #[Locked]
    public Service $record;

    public function mount(Service $service): void
    {
        $this->authorize('view', $service);
        $this->record = $service;
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);

        $this->record->delete();

        Flux::toast(variant: 'success', text: 'Service supprimé(e).');

        $this->redirectRoute('services.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('services.index')" wire:navigate class="text-sm">&larr; Services</flux:link>
            <flux:heading size="xl" level="1" class="mt-2">{{ $record->nom }}</flux:heading>
            <flux:text class="mt-1">
                Par {{ $record->user?->name }} · {{ $record->created_at->format('d/m/Y à H:i') }}
            </flux:text>
        </div>

        <div class="flex gap-2">
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('services.edit', $record)" wire:navigate>Modifier</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Supprimer définitivement cet élément ?">Supprimer</flux:button>
            @endcan
        </div>
    </div>



    <flux:card>
        <dl class="divide-y divide-zinc-200 dark:divide-zinc-700">
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Nom</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0">{{ $record->nom ?? '—' }}</dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Description</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0"><p class="whitespace-pre-line">{{ $record->description ?? '—' }}</p></dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Icone</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0">{{ $record->icone ?? '—' }}</dd>
            </div>
        </dl>
    </flux:card>
</section>
