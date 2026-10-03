<?php

use App\Models\Demarche;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Démarche')] class extends Component {
    #[Locked]
    public Demarche $record;

    /** Message facultatif de l'agent, joint au changement d'état (affiché dans le suivi et la notification). */
    public string $commentaire = '';

    public function mount(Demarche $demarche): void
    {
        $this->authorize('view', $demarche);
        $this->record = $demarche->loadMissing(['service', 'user', 'etapes']);
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);

        $this->record->delete();

        Flux::toast(variant: 'success', text: 'Démarche supprimée.');

        $this->redirectRoute('demarches.index', navigate: true);
    }

    public function changerStatut(string $statut): void
    {
        $this->authorize('changerStatut', $this->record);
        abort_unless(in_array($statut, Demarche::STATUT_OPTIONS, true), 422);
        $this->validate(['commentaire' => ['nullable', 'string', 'max:1000']]);

        $this->record->changerStatut($statut, $this->commentaire, auth()->user());
        $this->record->load('etapes');
        $this->reset('commentaire');

        Flux::toast(variant: 'success', text: 'Statut mis à jour. L’habitant a été prévenu.');
    }
}; ?>

@php
    $statut = $record->statut;
    // D11 : chronologie datée (dépôt, chaque changement d'état, étape suivante attendue).
    $chronologie = $record->chronologie();
    $estAuteur = $record->user_id === auth()->id();
@endphp

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        label="Démarche"
        :title="$record->titre"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Démarches' => route('demarches.index'), ($record->titre ?: 'Démarche') => null]"
    >
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-ink-2">
                <x-tn.status-badge :etat="$record->etatStatut()">{{ Demarche::libelleStatut($statut) }}</x-tn.status-badge>
                <span>Par {{ $record->user?->name }}</span>
                <span class="font-mono text-xs">{{ $record->created_at->format('d.m.Y · H:i') }}</span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('demarches.edit', $record)" wire:navigate>Modifier</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Supprimer définitivement cette démarche ?">Supprimer</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <x-audit-history :subject="$record" variant="resume" />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <x-tn.surface>
            <x-tn.section-label as="h2" class="mb-2">Détails</x-tn.section-label>
            <dl>
                <x-tn.field label="Objet">{{ $record->titre ?? '—' }}</x-tn.field>
                <x-tn.field label="Service">{{ $record->service?->nom ?? 'Non précisé' }}</x-tn.field>
                <x-tn.field label="Description"><p class="whitespace-pre-line leading-relaxed">{{ $record->description ?? '—' }}</p></x-tn.field>
            </dl>
        </x-tn.surface>

        <div class="flex flex-col gap-6">
            <flux:callout :icon="$statut === 'refusee' ? 'exclamation-triangle' : 'information-circle'" data-test="conseil-statut">
                <flux:callout.heading>{{ $estAuteur ? 'Ce que vous devez savoir' : 'Ce que l’habitant voit' }}</flux:callout.heading>
                <flux:callout.text>{{ Demarche::conseilStatut($statut) }}</flux:callout.text>
            </flux:callout>

            <x-tn.panel label="Suivi" padding="p-5 md:p-6">
                <x-tn.timeline :items="$chronologie" />
                @if ($estAuteur && $record->etapes->isNotEmpty())
                    <p class="mt-4 text-xs text-ink-2">Chaque changement d’état vous est aussi envoyé en notification et par e-mail.</p>
                @endif
            </x-tn.panel>

            @can('changerStatut', $record)
                <x-tn.surface>
                    <x-tn.section-label as="h2" class="mb-3">Changer le statut</x-tn.section-label>
                    <flux:textarea wire:model="commentaire" label="Message pour l’habitant (facultatif)" rows="2" placeholder="Ex. : votre carte est prête, à retirer à l’accueil de la mairie." class="mb-3" />
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (Demarche::STATUT_OPTIONS as $option)
                            <flux:button size="sm" wire:click="changerStatut('{{ $option }}')" wire:loading.attr="disabled" :disabled="$option === $statut" :variant="$option === $statut ? 'primary' : 'outline'">
                                {{ Demarche::libelleStatut($option) }}
                            </flux:button>
                        @endforeach
                    </div>
                </x-tn.surface>
            @endcan
        </div>
    </div>

    <x-audit-history :subject="$record" />
</section>
