<?php

use App\Models\Actualite;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Actualite')] class extends Component {
    #[Locked]
    public Actualite $record;

    public function mount(Actualite $actualite): void
    {
        $this->authorize('view', $actualite);
        $this->record = $actualite;
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);
        if ($this->record->image) {
            Storage::disk('public')->delete($this->record->image);
        }
        $this->record->delete();

        Flux::toast(variant: 'success', text: 'Actualite supprimé(e).');

        $this->redirectRoute('actualites.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('actualites.index')" wire:navigate class="text-sm">&larr; Actualites</flux:link>
            <flux:heading size="xl" level="1" class="mt-2">{{ $record->titre }}</flux:heading>
            <flux:text class="mt-1">
                Par {{ $record->user?->name }} · {{ $record->created_at->format('d/m/Y à H:i') }}
            </flux:text>
        </div>

        <div class="flex gap-2">
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('actualites.edit', $record)" wire:navigate>Modifier</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Supprimer définitivement cet élément ?">Supprimer</flux:button>
            @endcan
        </div>
    </div>

    @if ($record->image)
        <img src="{{ Storage::url($record->image) }}" alt="Image" class="max-h-96 w-full rounded-xl object-cover" />
    @endif

    <flux:card>
        <dl class="divide-y divide-zinc-200 dark:divide-zinc-700">
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Titre</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0">{{ $record->titre ?? '—' }}</dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Contenu</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0"><p class="whitespace-pre-line">{{ $record->contenu ?? '—' }}</p></dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Date</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0">{{ $record->date?->format('d/m/Y') ?? '—' }}</dd>
            </div>
        </dl>
    </flux:card>
</section>
