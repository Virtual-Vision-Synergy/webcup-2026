<?php

use App\Models\GenTestFiche;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::public'), Title('Gen Test Fiche')] class extends Component {
    #[Locked]
    public GenTestFiche $record;

    public function mount(GenTestFiche $genTestFiche): void
    {
        $this->authorize('view', $genTestFiche);
        $this->record = $genTestFiche->loadMissing(['genTestZone']);
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);

        $this->record->delete();

        Flux::toast(variant: 'success', text: 'Gen Test Fiche supprimé(e).');

        $this->redirectRoute('gen-test-fiches.index', navigate: true);
    }

    public function changerStatut(string $statut): void
    {
        $this->authorize('changerStatut', $this->record);
        abort_unless(in_array($statut, GenTestFiche::STATUT_OPTIONS, true), 422);

        $this->record->changerStatut($statut);

        Flux::toast(variant: 'success', text: 'Statut mis à jour.');
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('gen-test-fiches.index')" wire:navigate class="text-sm">&larr; Gen Test Fiches</flux:link>
            <flux:heading size="xl" level="1" class="mt-2">{{ $record->titre }}</flux:heading>
            <flux:text class="mt-1">
                @auth Par {{ $record->user?->name }} · @endauth{{ $record->created_at->format('d/m/Y à H:i') }}
            </flux:text>
            <div class="mt-2"><flux:badge size="sm" :color="$record->couleurStatut()">{{ \App\Models\GenTestFiche::libelleStatut($record->statut) }}</flux:badge></div>
        </div>

        <div class="flex gap-2">
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('gen-test-fiches.edit', $record)" wire:navigate>Modifier</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Supprimer définitivement cet élément ?">Supprimer</flux:button>
            @endcan
        </div>
    </div>



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
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Gen Test Zone</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0">{{ $record->genTestZone?->nom ?? '—' }}</dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Statut</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0"><flux:badge size="sm" :color="$record->couleurStatut()">{{ \App\Models\GenTestFiche::libelleStatut($record->statut) }}</flux:badge></dd>
            </div>
        </dl>
    </flux:card>

    @can('changerStatut', $record)
        <flux:card class="space-y-3">
            <flux:heading size="sm">Changer le statut</flux:heading>
            <div class="flex flex-wrap gap-2">
                @foreach (\App\Models\GenTestFiche::STATUT_OPTIONS as $option)
                    <flux:button size="sm" wire:click="changerStatut('{{ $option }}')" :disabled="$option === $record->statut">{{ \App\Models\GenTestFiche::libelleStatut($option) }}</flux:button>
                @endforeach
            </div>
        </flux:card>
    @endcan
</section>
