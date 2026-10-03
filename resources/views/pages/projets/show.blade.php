<?php

use App\Models\Projet;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Fiche publique d'un projet de la ville (F67) : description, étapes, avancement et lieu.
 */
new #[Layout('layouts::public'), Title('Projet de la ville')] class extends Component {
    #[Locked]
    public Projet $record;

    public function mount(Projet $projet): void
    {
        $this->authorize('view', $projet);
        $this->record = $projet->load('quartier:id,nom');
    }
}; ?>

@php
    $etapes = $record->listeEtapes();
    $terminees = $record->etat === 'termine' ? count($etapes) : min($record->etapes_terminees, count($etapes));
    $avancement = $record->avancement();
    $point = $record->pointCarte($record->titre);
@endphp

<section class="mx-auto w-full max-w-5xl space-y-6 px-4 py-6 lg:px-8">
    <x-tn.page-header
        label="Projet de la ville"
        :title="$record->titre"
        :subtitle="$record->quartier ? 'Quartier '.$record->quartier->nom : 'Toute la ville de Nova Terra'"
        :breadcrumb="['Projets de la ville' => route('projets.index'), $record->titre => null]"
    >
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <x-tn.status-badge :etat="$record->badgeEtat()">{{ Projet::libelleEtat($record->etat) }}</x-tn.status-badge>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('agent.projets.edit', $record)">Mettre à jour</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <div class="space-y-6">
            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-3">Le projet en quelques mots</x-tn.section-label>
                <p class="whitespace-pre-line leading-relaxed text-ink">{{ $record->description }}</p>
            </x-tn.surface>

            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-3">Étapes et avancement</x-tn.section-label>

                <div class="flex justify-between text-sm text-ink-2">
                    <span>{{ count($etapes) > 0 ? $terminees.' étape(s) sur '.count($etapes).' réalisée(s)' : 'Avancement' }}</span>
                    <span class="font-mono text-ink">{{ $avancement }} %</span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-line" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $avancement }}" aria-label="Avancement du projet">
                    <div class="h-2 rounded-full bg-cyan" style="width: {{ $avancement }}%"></div>
                </div>

                @if ($etapes === [])
                    <p class="mt-4 text-ink-2">Les étapes du projet seront bientôt publiées.</p>
                @else
                    <ol class="mt-5 space-y-3">
                        @foreach ($etapes as $i => $etape)
                            @php($statut = $i < $terminees ? 'fait' : ($i === $terminees && $record->etat === 'en_cours' ? 'en_cours' : 'a_venir'))
                            <li class="flex items-start gap-3">
                                <span @class([
                                    'flex size-7 shrink-0 items-center justify-center rounded-full border font-mono text-xs',
                                    'border-green/40 bg-green/10 text-green' => $statut === 'fait',
                                    'border-amber/40 bg-amber/10 text-amber' => $statut === 'en_cours',
                                    'border-line text-ink-2' => $statut === 'a_venir',
                                ]) aria-hidden="true">
                                    @if ($statut === 'fait')
                                        <flux:icon name="check" variant="micro" class="size-4" />
                                    @else
                                        {{ $i + 1 }}
                                    @endif
                                </span>
                                <div class="pt-0.5">
                                    <p @class(['font-medium', 'text-ink' => $statut !== 'a_venir', 'text-ink-2' => $statut === 'a_venir'])>{{ $etape }}</p>
                                    <p class="text-xs text-ink-2">{{ match ($statut) { 'fait' => 'Réalisée', 'en_cours' => 'En cours', default => 'À venir' } }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-tn.surface>
        </div>

        <x-tn.panel label="Infos pratiques" padding="p-5 md:p-6">
            <dl>
                <x-tn.field label="Quartier"><p class="text-sm">{{ $record->quartier?->nom ?? 'Toute la ville' }}</p></x-tn.field>
                <x-tn.field label="Début"><p class="text-sm">{{ $record->date_debut?->translatedFormat('d F Y') ?? 'À définir' }}</p></x-tn.field>
                <x-tn.field label="Fin prévue"><p class="text-sm">{{ $record->date_fin?->translatedFormat('d F Y') ?? 'À définir' }}</p></x-tn.field>
                @if ($budget = $record->budgetFormate())
                    <x-tn.field label="Budget"><p class="font-mono text-sm">{{ $budget }}</p></x-tn.field>
                @endif
            </dl>

            @if ($point)
                <x-carte :points="[$point]" hauteur="14rem" :zoom="15" :label="'Emplacement du projet : '.$record->titre" class="mt-4" />
            @else
                <p class="mt-4 text-sm text-ink-2">Le lieu exact sera précisé prochainement.</p>
            @endif
        </x-tn.panel>
    </div>

    <flux:button icon="arrow-left" variant="ghost" :href="route('projets.index')" wire:navigate>Tous les projets</flux:button>
</section>
