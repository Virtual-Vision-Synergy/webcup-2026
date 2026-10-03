<?php

use App\Models\Message;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Message')] class extends Component {
    #[Locked]
    public Message $record;

    public function mount(Message $message): void
    {
        $this->authorize('view', $message);
        $this->record = $message;
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);

        $this->record->delete();

        Flux::toast(variant: 'success', text: 'Message supprimé(e).');

        $this->redirectRoute('messages.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('messages.index')" wire:navigate class="text-sm">&larr; Messages</flux:link>
            <flux:heading size="xl" level="1" class="mt-2">{{ $record->nom }}</flux:heading>
            <flux:text class="mt-1">
                Par {{ $record->user?->name }} · {{ $record->created_at->format('d/m/Y à H:i') }}
            </flux:text>
        </div>

        <div class="flex gap-2">
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('messages.edit', $record)" wire:navigate>Modifier</flux:button>
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
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Email</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0">{{ $record->email ?? '—' }}</dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Sujet</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0">{{ $record->sujet ?? '—' }}</dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Message</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0"><p class="whitespace-pre-line">{{ $record->message ?? '—' }}</p></dd>
            </div>
        </dl>
    </flux:card>
</section>
