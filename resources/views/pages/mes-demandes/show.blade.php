<?php

use App\Models\AuditLog;
use App\Models\Signalement;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Suivi de ma demande')] class extends Component {
    #[Locked]
    public Signalement $record;

    public function mount(Signalement $signalement): void
    {
        $this->authorize('viewOwn', $signalement);
        $this->record = $signalement->load('etapes');
    }

    /**
     * Rafraîchissement léger (wire:poll) : une nouvelle étape ajoutée par la mairie apparaît sans recharger la page.
     */
    public function rafraichir(): void
    {
        $this->authorize('viewOwn', $this->record);
        $this->record->refresh()->load('etapes');
    }
}; ?>

@php
    $categorie = Signalement::libelleCategorie($record->categorie);
    $chronologie = $record->chronologie();
@endphp

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        label="Mes demandes"
        :title="$categorie"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Mes demandes' => route('mes-demandes.index'), $categorie => null]"
    >
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-ink-2">
                <span class="sr-only">État actuel :</span>
                <flux:badge :color="$record->statutCouleur()" size="sm">{{ $record->statutLibelle() }}</flux:badge>
                <span class="font-mono text-xs">Déposée le {{ $record->created_at->copy()->setTimezone(AuditLog::FUSEAU)->locale('fr')->translatedFormat('j F Y à H:i') }}</span>
            </div>
        </x-slot:meta>
    </x-tn.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <x-tn.surface>
            <x-tn.section-label as="h2" class="mb-2">Ma demande</x-tn.section-label>
            <dl>
                <x-tn.field label="Catégorie">{{ $categorie }}</x-tn.field>
                <x-tn.field label="Lieu">{{ $record->lieu ?: 'Lieu non précisé' }}</x-tn.field>
                <x-tn.field label="Description"><p class="whitespace-pre-line leading-relaxed">{{ $record->description }}</p></x-tn.field>
            </dl>
            @if ($record->photo)
                <img src="{{ Storage::url($record->photo) }}" alt="Photo du problème signalé : {{ $categorie }}, {{ $record->lieu }}" class="mt-4 max-h-96 w-full rounded-xl object-cover" />
            @endif
        </x-tn.surface>

        <x-tn.panel label="Suivi" padding="p-5 md:p-6">
            <div wire:poll.30s.visible="rafraichir">
                <h2 class="sr-only">Chronologie du traitement</h2>
                {{-- La prochaine étape attendue est affichée en grisé, sans date. --}}
                <x-tn.timeline
                    :items="$record->prochaineEtape() ? [...$chronologie, ['label' => 'Prochaine étape : '.$record->prochaineEtape(), 'fait' => false]] : $chronologie"
                    aria-label="Étapes du traitement de ma demande"
                />
            </div>
        </x-tn.panel>
    </div>

    <div>
        <flux:button icon="arrow-left" variant="ghost" :href="route('mes-demandes.index')" wire:navigate>Retour à mes demandes</flux:button>
    </div>
</section>
