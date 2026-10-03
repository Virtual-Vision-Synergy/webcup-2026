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
        $this->record = $signalement->loadMissing('user');
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);

        if ($this->record->photo) {
            Storage::disk('public')->delete($this->record->photo);
        }

        $this->record->delete();

        Flux::toast(variant: 'success', text: 'Signalement supprimé.');

        $this->redirectRoute('signalements.index', navigate: true);
    }

    public function changerStatut(string $statut): void
    {
        $this->authorize('changerStatut', $this->record);
        abort_unless(in_array($statut, Signalement::STATUT_OPTIONS, true), 422);

        $this->record->changerStatut($statut);

        Flux::toast(variant: 'success', text: 'État mis à jour.');
    }
}; ?>

@php
    $statut = $record->statut;
    $categorie = Signalement::libelleCategorie($record->categorie);
@endphp

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        label="Signalement"
        :title="$categorie"
        :breadcrumb="['Signalements' => route('signalements.index'), $categorie => null]"
    >
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-ink-2">
                <x-tn.status-badge :etat="$record->etatStatut()">{{ Signalement::libelleStatut($statut) }}</x-tn.status-badge>
                <span>Par {{ $record->user?->name }}</span>
                <span class="font-mono text-xs">{{ $record->created_at->format('d.m.Y · H:i') }}</span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('signalements.edit', $record)" wire:navigate>Modifier</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Supprimer définitivement ce signalement ?">Supprimer</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <x-tn.surface>
            <x-tn.section-label as="h2" class="mb-2">Détails</x-tn.section-label>
            <dl>
                <x-tn.field label="Catégorie">{{ $categorie }}</x-tn.field>
                <x-tn.field label="Lieu">{{ $record->lieu }}</x-tn.field>
                <x-tn.field label="Description"><p class="whitespace-pre-line leading-relaxed">{{ $record->description }}</p></x-tn.field>
            </dl>
            @if ($record->photo)
                <img src="{{ Storage::url($record->photo) }}" alt="Photo du problème signalé : {{ $categorie }}, {{ $record->lieu }}" class="mt-4 max-h-96 w-full rounded-xl object-cover" />
            @endif
        </x-tn.surface>

        <div class="flex flex-col gap-6">
            @can('changerStatut', $record)
                <x-tn.surface>
                    <x-tn.section-label as="h2" class="mb-3">Changer l'état</x-tn.section-label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (Signalement::STATUT_OPTIONS as $option)
                            <flux:button size="sm" wire:click="changerStatut('{{ $option }}')" :disabled="$option === $statut" :variant="$option === $statut ? 'primary' : 'outline'">
                                {{ Signalement::libelleStatut($option) }}
                            </flux:button>
                        @endforeach
                    </div>
                </x-tn.surface>
            @else
                <x-tn.surface>
                    <x-tn.section-label as="h2" class="mb-2">Suivi</x-tn.section-label>
                    <p class="text-ink-2">Votre signalement a été transmis à la mairie. Son état évoluera ici dès qu'un agent l'aura pris en charge.</p>
                </x-tn.surface>
            @endcan
        </div>
    </div>
</section>
