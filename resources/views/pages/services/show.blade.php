<?php

use App\Models\Demarche;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\ServiceInterruption;
use App\Services\OnboardingProgress;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Service')] class extends Component {
    #[Locked]
    public Service $record;

    public function mount(Service $service): void
    {
        $this->authorize('view', $service);
        $this->record = $service->load('interruptionCourante.alternativeService');

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

    /**
     * Interruption en cours (F38) : seules les informations publiques sont envoyées à la vue (jamais l'agent).
     */
    #[Computed]
    public function interruption(): ?ServiceInterruption
    {
        return $this->record->interruptionEnCours();
    }

    #[Computed]
    public function prendRendezVous(): bool
    {
        return (int) $this->record->duree_rendez_vous > 0;
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

    @if (session('service-indisponible'))
        <flux:callout variant="danger" icon="exclamation-triangle" role="alert">
            <flux:callout.text>{{ session('service-indisponible') }}</flux:callout.text>
        </flux:callout>
    @endif

    @php($interruption = $this->interruption)

    {{-- F38 : statut du service, AVANT tout bouton de démarche. --}}
    @if ($interruption)
        <div role="status" @class([
            'space-y-4 rounded-md border p-5 md:p-6',
            'border-magenta/35 bg-magenta/8' => $interruption->type === 'incident',
            'border-amber/35 bg-amber/8' => $interruption->type !== 'incident',
        ]) data-test="interruption">
            <div class="flex flex-wrap items-center gap-2">
                <x-tn.status-badge :etat="$interruption->etatBadge()">Indisponible · {{ $interruption->libelleType() }}</x-tn.status-badge>
                <span class="text-sm text-ink-2">Depuis le {{ ServiceInterruption::libelleDate($interruption->debut_at) }}</span>
            </div>
            <div>
                <h2 class="tn-display text-lg font-semibold text-ink">Ce service est momentanément indisponible</h2>
                <p class="mt-1 text-ink">{{ $interruption->motif }}</p>
                <p class="mt-2 font-medium text-ink">{{ $interruption->libelleRetour() }}</p>
            </div>
            <div class="rounded-sm border border-line bg-surface p-4">
                <h3 class="font-semibold text-ink">Que faire en attendant ?</h3>
                <p class="mt-1 whitespace-pre-line text-ink">{{ $interruption->alternative }}</p>
                @if ($interruption->alternativeService)
                    <flux:link :href="route('services.show', $interruption->alternativeService)" wire:navigate class="mt-2 inline-flex items-center gap-1">
                        Voir le service {{ $interruption->alternativeService->nom }}
                    </flux:link>
                @endif
            </div>
            <p class="text-sm text-ink-2">Les rendez-vous déjà pris pendant cette période seront confirmés par la mairie. Les démarches déjà déposées restent enregistrées.</p>
        </div>
    @endif

    {{-- Démarches possibles depuis ce service (F39, D12) : suspendues pendant une interruption (contrôle serveur aussi). --}}
    <div class="flex flex-wrap items-center gap-3">
        @if ($interruption)
            @if ($this->prendRendezVous)
                <flux:button icon="calendar-days" disabled>Prendre rendez-vous</flux:button>
            @endif
            <flux:button icon="document-plus" disabled>Faire une demande</flux:button>
            <p class="text-sm font-medium text-ink-2">Démarche suspendue pendant l’interruption</p>
        @else
            @if ($this->prendRendezVous)
                @can('create', RendezVous::class)
                    <flux:button variant="primary" icon="calendar-days" :href="route('appointments.create', ['service' => $record->slug])" wire:navigate>Prendre rendez-vous</flux:button>
                @endcan
            @endif
            @can('create', Demarche::class)
                <flux:button icon="document-plus" :href="route('demarches.create', ['service' => $record->id])" wire:navigate>Faire une demande</flux:button>
            @endcan
        @endif
    </div>

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
            @if (! $record->horaires && ! $record->telephone && ! $record->email && ! $record->adresse)
                <p class="text-ink-2">{{ __('Les informations pratiques seront publiées prochainement.') }}</p>
            @endif
        </x-tn.panel>
    </div>

    <x-audit-history :subject="$record" />
</section>
