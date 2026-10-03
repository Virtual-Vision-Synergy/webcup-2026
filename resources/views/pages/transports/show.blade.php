<?php

use App\Models\LigneTransport;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ligne de transport')] class extends Component {
    #[Locked]
    public LigneTransport $record;

    public function mount(LigneTransport $ligneTransport): void
    {
        $this->authorize('view', $ligneTransport);
        $this->record = $ligneTransport;
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);

        $this->record->delete();

        Flux::toast(variant: 'success', text: 'Ligne supprimée.');

        $this->redirectRoute('transports.index', navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    @php($arrets = $record->listeArrets())

    <x-tn.page-header
        :label="$record->modeLabel().' · Ligne '.$record->numero"
        :title="$record->nom"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Transports' => route('transports.index'), 'Ligne '.$record->numero => null]"
    >
        <x-slot:actions>
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('transports.edit', $record)" wire:navigate>Modifier</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Supprimer définitivement cette ligne ?">Supprimer</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <x-audit-history :subject="$record" variant="resume" />

    @if ($record->estPerturbee())
        <div role="alert" @class([
            'flex items-start gap-3 rounded-md border p-4',
            'border-amber/40 bg-amber/8 text-amber' => $record->etat === 'perturbe',
            'border-magenta/40 bg-magenta/8 text-magenta' => $record->etat === 'interrompu',
        ])>
            <flux:icon :name="$record->etat === 'interrompu' ? 'x-circle' : 'exclamation-triangle'" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div>
                <p class="font-semibold">{{ $record->etatLabel() }}</p>
                <p class="mt-1 whitespace-pre-line text-sm">{{ $record->perturbation ?? 'Perturbation signalée, informations à venir.' }}</p>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <x-tn.panel label="Horaires et fréquence" padding="p-5 md:p-6">
            <dl>
                <x-tn.field label="État">
                    <x-tn.status-badge :etat="$record->etatBadge()" :live="$record->estPerturbee()">{{ $record->etatLabel() }}</x-tn.status-badge>
                </x-tn.field>
                <x-tn.field label="Horaires"><p class="whitespace-pre-line font-mono text-sm leading-6">{{ $record->horaires }}</p></x-tn.field>
                @if ($record->frequence)
                    <x-tn.field label="Fréquence"><p class="font-mono text-sm">{{ $record->frequence }}</p></x-tn.field>
                @endif
                @if (count($arrets) > 0)
                    <x-tn.field label="Trajet"><p class="text-sm">{{ $arrets[0] }} → {{ end($arrets) }}</p></x-tn.field>
                @endif
            </dl>
        </x-tn.panel>

        <x-tn.surface>
            <x-tn.section-label as="h2" class="mb-4">Arrêts desservis ({{ count($arrets) }})</x-tn.section-label>
            @if (count($arrets) > 0)
                <x-tn.timeline :items="collect($arrets)->map(fn (string $arret, int $i) => [
                    'label' => $arret,
                    'texte' => $i === 0 ? 'Départ' : ($i === count($arrets) - 1 ? 'Terminus' : null),
                    'etat' => $record->etatBadge(),
                ])->all()" />
            @else
                <p class="text-ink-2">La liste des arrêts sera publiée prochainement.</p>
            @endif
        </x-tn.surface>
    </div>

    <x-audit-history :subject="$record" />
</section>
