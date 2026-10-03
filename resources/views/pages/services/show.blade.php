<?php

use App\Models\Service;
use App\Services\OnboardingProgress;
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

        // Parcours de prise en main (D12), étape « Trouver un service » : sans effet hors parcours en cours.
        OnboardingProgress::pour(auth()->user())->marquerServiceVisite($service);
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);

        $this->record->delete();

        Flux::toast(variant: 'success', text: 'Service supprimé(e).');

        $this->redirectRoute('services.index', navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        label="Service municipal"
        :title="$record->nom"
        :breadcrumb="['Services' => route('services.index'), $record->nom => null]"
    >
        <x-slot:actions>
            @if (Route::has('messages.create'))
                <flux:button variant="primary" icon="mail" :href="route('messages.create')" class="tn-cta" wire:navigate>Écrire au service</flux:button>
            @endif
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('services.edit', $record)" wire:navigate>Modifier</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Supprimer définitivement ce service ?">Supprimer</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <x-tn.surface>
            <x-tn.section-label as="h2" class="mb-3">Missions</x-tn.section-label>
            <p class="whitespace-pre-line leading-relaxed text-ink">{{ $record->description ?? 'Description à venir.' }}</p>
        </x-tn.surface>

        <x-tn.panel label="Infos pratiques" padding="p-5 md:p-6">
            <dl>
                @if ($record->horaires)
                    <x-tn.field label="Horaires"><p class="whitespace-pre-line font-mono text-sm leading-6">{{ $record->horaires }}</p></x-tn.field>
                @endif
                @if ($record->telephone)
                    <x-tn.field label="Téléphone"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $record->telephone) }}" class="font-mono text-sm text-cyan hover:underline">{{ $record->telephone }}</a></x-tn.field>
                @endif
                @if ($record->email)
                    <x-tn.field label="E-mail"><a href="mailto:{{ $record->email }}" class="break-all text-sm text-cyan hover:underline">{{ $record->email }}</a></x-tn.field>
                @endif
                @if ($record->adresse)
                    <x-tn.field label="Adresse"><p class="whitespace-pre-line text-sm">{{ $record->adresse }}</p></x-tn.field>
                @endif
            </dl>
            @if (! $record->horaires && ! $record->telephone && ! $record->email && ! $record->adresse)
                <p class="text-ink-2">Les informations pratiques seront publiées prochainement.</p>
            @endif
        </x-tn.panel>
    </div>
</section>
