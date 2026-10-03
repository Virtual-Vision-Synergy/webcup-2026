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

    public function mount(Demarche $demarche): void
    {
        $this->authorize('view', $demarche);
        $this->record = $demarche->loadMissing(['service', 'user']);
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

        $this->record->changerStatut($statut);

        Flux::toast(variant: 'success', text: 'Statut mis à jour.');
    }
}; ?>

@php
    $statut = $record->statut;
    $avance = in_array($statut, ['en_cours', 'traitee', 'refusee'], true);
    $termine = in_array($statut, ['traitee', 'refusee'], true);
    // Chronologie : seules les dates réellement connues sont affichées (dépôt, dernière mise à jour).
    $chronologie = [
        ['label' => __('Démarche déposée'), 'date' => $record->created_at, 'etat' => 'info', 'fait' => true],
        ['label' => __('Prise en charge par le service'), 'date' => $statut === 'en_cours' ? $record->updated_at : null, 'etat' => 'info', 'fait' => $avance, 'texte' => $avance ? null : __('En attente d’un agent municipal.')],
        [
            'label' => $termine ? __('Décision').' : '.__(Demarche::libelleStatut($statut)) : __('Décision'),
            'date' => $termine ? $record->updated_at : null,
            'etat' => $record->etatStatut(),
            'fait' => $termine,
            'texte' => $termine ? null : __('La décision apparaîtra ici.'),
        ],
    ];
@endphp

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        :label="__('Démarche')"
        :title="$record->titre"
<<<<<<< HEAD
        :breadcrumb="[__('Mon espace') => route('dashboard'), __('Démarches') => route('demarches.index'), ($record->titre ?: __('Démarche')) => null]"
=======
        :breadcrumb="['Mon espace' => route('dashboard'), 'Démarches' => route('demarches.index'), ($record->titre ?: 'Démarche') => null]"
>>>>>>> origin/main
    >
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-ink-2">
                <x-tn.status-badge :etat="$record->etatStatut()">{{ __(Demarche::libelleStatut($statut)) }}</x-tn.status-badge>
                <span>Par {{ $record->user?->name }}</span>
                <span class="font-mono text-xs">{{ $record->created_at->format('d.m.Y · H:i') }}</span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('demarches.edit', $record)" wire:navigate>{{ __('Modifier') }}</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Supprimer définitivement cette démarche ?') }}">{{ __('Supprimer') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <x-tn.surface>
            <x-tn.section-label as="h2" class="mb-2">{{ __('Détails') }}</x-tn.section-label>
            <dl>
                <x-tn.field :label="__('Objet')">{{ $record->titre ?? '—' }}</x-tn.field>
                <x-tn.field :label="__('Service')">{{ $record->service?->nom ? __($record->service->nom) : __('Non précisé') }}</x-tn.field>
                <x-tn.field :label="__('Description')"><p class="whitespace-pre-line leading-relaxed">{{ $record->description ?? '—' }}</p></x-tn.field>
            </dl>
        </x-tn.surface>

        <div class="flex flex-col gap-6">
            <x-tn.panel :label="__('Suivi')" padding="p-5 md:p-6">
                <x-tn.timeline :items="$chronologie" />
            </x-tn.panel>

            @can('changerStatut', $record)
                <x-tn.surface>
                    <x-tn.section-label as="h2" class="mb-3">{{ __('Changer le statut') }}</x-tn.section-label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (Demarche::STATUT_OPTIONS as $option)
                            <flux:button size="sm" wire:click="changerStatut('{{ $option }}')" :disabled="$option === $statut" :variant="$option === $statut ? 'primary' : 'outline'">
                                {{ __(Demarche::libelleStatut($option)) }}
                            </flux:button>
                        @endforeach
                    </div>
                </x-tn.surface>
            @endcan
        </div>
    </div>
</section>
