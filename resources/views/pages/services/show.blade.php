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

        Flux::toast(variant: 'success', text: __('Service supprimé(e).'));

        $this->redirectRoute('services.index', navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        :label="__(Service::labelCategorie($record->categorie) ?? __('Service municipal'))"
        :title="__($record->nom)"
        :breadcrumb="[__('Mon espace') => route('dashboard'), __('Services') => route('services.index'), __($record->nom) => null]"
    >
        <x-slot:actions>
            @if (Route::has('messages.create'))
                <flux:button variant="primary" icon="mail" :href="route('messages.create')" class="tn-cta" wire:navigate>{{ __('Écrire au service') }}</flux:button>
            @endif
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('services.edit', $record)" wire:navigate>{{ __('Modifier') }}</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Supprimer définitivement ce service ?') }}">{{ __('Supprimer') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <x-audit-history :subject="$record" variant="resume" />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <x-tn.surface>
            <x-tn.section-label as="h2" class="mb-3">{{ __('Missions') }}</x-tn.section-label>
            <p class="whitespace-pre-line leading-relaxed text-ink">{{ __($record->description ?? __('Description à venir.')) }}</p>
        </x-tn.surface>

        <x-tn.panel :label="__('Infos pratiques')" padding="p-5 md:p-6">
            <dl>
                @if ($record->horaires)
                    <x-tn.field :label="__('Horaires')"><p class="whitespace-pre-line font-mono text-sm leading-6">{{ __($record->horaires) }}</p></x-tn.field>
                @endif
                @if ($record->telephone)
                    <x-tn.field :label="__('Téléphone')"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $record->telephone) }}" class="font-mono text-sm text-cyan hover:underline">{{ $record->telephone }}</a></x-tn.field>
                @endif
                @if ($record->email)
                    <x-tn.field :label="__('E-mail')"><a href="mailto:{{ $record->email }}" class="break-all text-sm text-cyan hover:underline">{{ $record->email }}</a></x-tn.field>
                @endif
                @if ($record->adresse)
                    <x-tn.field :label="__('Adresse')"><p class="whitespace-pre-line text-sm">{{ __($record->adresse) }}</p></x-tn.field>
                @endif
            </dl>
            @if ($point = $record->pointCarte(__($record->nom)))
                <x-carte :points="[$point]" hauteur="14rem" :zoom="16" :label="__('Emplacement de :nom', ['nom' => __($record->nom)])" class="mt-4" />
                <a href="https://www.openstreetmap.org/directions?to={{ $record->latitude }}%2C{{ $record->longitude }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex items-center gap-1 text-sm text-cyan hover:underline">
                    <flux:icon name="arrow-top-right-on-square" class="size-4" />{{ __('Itinéraire (nouvel onglet)') }}
                </a>
            @endif
            @if (! $record->horaires && ! $record->telephone && ! $record->email && ! $record->adresse)
                <p class="text-ink-2">{{ __('Les informations pratiques seront publiées prochainement.') }}</p>
            @endif
        </x-tn.panel>
    </div>

    <x-audit-history :subject="$record" />
</section>
