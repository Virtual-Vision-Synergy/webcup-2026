<?php

use App\Models\Service;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::public'), Title('Service municipal')] class extends Component {
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

        Flux::toast(variant: 'success', text: 'Service municipal supprimé.');

        $this->redirectRoute('services.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('services.index')" wire:navigate class="text-sm">&larr; Services municipaux</flux:link>
            <flux:heading size="xl" level="1" class="mt-2">{{ $record->nom }}</flux:heading>
            <flux:text class="mt-1">Mairie de Nova Terra</flux:text>
        </div>

        <div class="flex gap-2">
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('services.edit', $record)" wire:navigate>Modifier</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Supprimer définitivement ce service ?">Supprimer</flux:button>
            @endcan
        </div>
    </div>

    <flux:card class="space-y-2">
        <flux:heading size="lg">Présentation</flux:heading>
        <flux:text class="whitespace-pre-line">{{ $record->description }}</flux:text>
    </flux:card>

    <div class="grid gap-6 md:grid-cols-2">
        <flux:card class="space-y-2">
            <div class="flex items-center gap-2">
                <flux:icon.clock class="size-5" />
                <flux:heading size="lg">Horaires d'ouverture</flux:heading>
            </div>
            <flux:text class="whitespace-pre-line">{{ $record->horaires ?? 'Non renseignés' }}</flux:text>
        </flux:card>

        <flux:card class="space-y-3">
            <div class="flex items-center gap-2">
                <flux:icon.phone class="size-5" />
                <flux:heading size="lg">Contact</flux:heading>
            </div>

            @if (! $record->telephone && ! $record->email && ! $record->adresse)
                <flux:text>Non renseigné</flux:text>
            @endif

            @if ($record->telephone)
                <div class="flex items-center gap-2 text-sm">
                    <flux:icon.phone class="size-4 shrink-0" />
                    <flux:link href="tel:{{ preg_replace('/[^0-9+]/', '', $record->telephone) }}">{{ $record->telephone }}</flux:link>
                </div>
            @endif

            @if ($record->email)
                <div class="flex items-center gap-2 text-sm">
                    <flux:icon.envelope class="size-4 shrink-0" />
                    <flux:link href="mailto:{{ $record->email }}">{{ $record->email }}</flux:link>
                </div>
            @endif

            @if ($record->adresse)
                <div class="flex items-center gap-2 text-sm">
                    <flux:icon.map-pin class="size-4 shrink-0" />
                    <span>{{ $record->adresse }}</span>
                </div>
            @endif
        </flux:card>
    </div>
</section>
