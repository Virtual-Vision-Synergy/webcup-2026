<?php

use App\Models\Signalement;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Signalement')] class extends Component {
    #[Locked]
    public Signalement $record;

    public function mount(Signalement $signalement): void
    {
        $this->authorize('view', $signalement);
        $this->record = $signalement;
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);
        if ($this->record->photo) {
            Storage::disk('public')->delete($this->record->photo);
        }
        $this->record->delete();

        Flux::toast(variant: 'success', text: 'Signalement supprimé(e).');

        $this->redirectRoute('signalements.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('signalements.index')" wire:navigate class="text-sm">&larr; Signalements</flux:link>
            <flux:heading size="xl" level="1" class="mt-2">{{ $record->titre }}</flux:heading>
            <flux:text class="mt-1">
                Par {{ $record->user?->name }} · {{ $record->created_at->format('d/m/Y à H:i') }}
            </flux:text>
        </div>

        <div class="flex gap-2">
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('signalements.edit', $record)" wire:navigate>Modifier</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Supprimer définitivement cet élément ?">Supprimer</flux:button>
            @endcan
        </div>
    </div>

    @if ($record->photo)
        <img src="{{ Storage::url($record->photo) }}" alt="Photo" class="max-h-96 w-full rounded-xl object-cover" />
    @endif

    <flux:card>
        <dl class="divide-y divide-zinc-200 dark:divide-zinc-700">
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Titre</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0">{{ $record->titre ?? '—' }}</dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Description</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0"><p class="whitespace-pre-line">{{ $record->description ?? '—' }}</p></dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Niveau</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0"><flux:badge size="sm">{{ ucfirst($record->niveau ?? '—') }}</flux:badge></dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Zone</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0">{{ $record->zone ?? '—' }}</dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Date incident</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0">{{ $record->date_incident?->format('d/m/Y') ?? '—' }}</dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Latitude</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0">{{ $record->latitude ?? '—' }}</dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Longitude</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0">{{ $record->longitude ?? '—' }}</dd>
            </div>
        </dl>
    </flux:card>
</section>
