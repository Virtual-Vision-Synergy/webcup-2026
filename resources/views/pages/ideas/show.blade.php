<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\Idea;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Fiche publique d'une idée (F68). Idée masquée par la modération : 404 pour les visiteurs,
 * visible par son auteur (avec la mention de modération) et par le personnel.
 */
new #[Layout('layouts::public'), Title('Idée')] class extends Component {
    use ThrottlesPerUser;

    #[Locked]
    public Idea $record;

    public function mount(Idea $idea): void
    {
        // 404 plutôt que 403 : on ne révèle pas l'existence d'une idée masquée.
        abort_unless(Gate::allows('view', $idea), 404);
        $this->record = $idea;
    }

    public function soutenir(): void
    {
        if (! auth()->check()) {
            $this->redirectRoute('login');

            return;
        }

        $this->authorize('soutenir', $this->record);
        $this->throttlePerUser('soutien', maxAttempts: 30, decaySeconds: 60);

        $this->record->ajouterSoutien(auth()->user());

        Flux::toast(variant: 'success', text: 'Votre soutien a bien été pris en compte.');
    }

    public function retirerSoutien(): void
    {
        if (! auth()->check()) {
            $this->redirectRoute('login');

            return;
        }

        $this->authorize('retirerSoutien', $this->record);
        $this->throttlePerUser('soutien', maxAttempts: 30, decaySeconds: 60);

        $this->record->retirerSoutien(auth()->user());

        Flux::toast(text: 'Votre soutien a été retiré.');
    }
}; ?>

@php
    $nombreSoutiens = $record->soutiens()->count();
    $estAuteur = auth()->check() && $record->user_id === auth()->id();
    $soutenue = auth()->check() && $record->estSoutenuPar(auth()->user());
@endphp

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        label="Boîte à idées"
        :title="$record->title"
        :breadcrumb="['Boîte à idées' => route('ideas.index'), $record->reference => null]"
    >
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-ink-2">
                <x-tn.status-badge :etat="$record->etatStatut()">{{ Idea::libelleStatut($record->status) }}</x-tn.status-badge>
                <span class="font-mono text-xs">{{ $record->reference }}</span>
                <span>{{ Idea::libelleCategorie($record->category) }}</span>
                <span class="inline-flex items-center gap-1"><flux:icon.users variant="micro" /> {{ $nombreSoutiens }} {{ $nombreSoutiens > 1 ? 'habitants soutiennent' : 'habitant soutient' }} cette idée</span>
            </div>
        </x-slot:meta>
    </x-tn.page-header>

    @if ($record->estMasquee())
        <flux:callout variant="warning" icon="eye-slash" heading="Masquée par la modération" text="Cette idée n’apparaît plus sur la page publique. Seuls vous et les agents de la ville pouvez la voir." />
    @endif

    @error('throttle')
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" />
    @enderror

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <div class="flex flex-col gap-6">
            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-2">L’idée</x-tn.section-label>
                <p class="whitespace-pre-line leading-relaxed text-ink" data-test="description">{{ $record->description }}</p>
                <p class="mt-3 font-mono text-xs text-ink-2">Proposée le {{ Idea::dateLocale($record->created_at) }}</p>
            </x-tn.surface>

            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-2">Réponse de la ville</x-tn.section-label>
                @if ($record->response !== null)
                    <p class="whitespace-pre-line leading-relaxed text-ink" data-test="response">{{ $record->response }}</p>
                    <p class="mt-3 font-mono text-xs text-ink-2">Réponse du {{ Idea::dateLocale($record->responded_at) }}</p>
                @else
                    <p class="text-ink-2">Pas encore de réponse. Un agent de la ville étudiera cette idée.</p>
                @endif
            </x-tn.surface>
        </div>

        <div class="flex flex-col gap-6">
            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-3">Soutiens</x-tn.section-label>
                @if ($soutenue)
                    <p class="inline-flex items-center gap-1.5 rounded-md border border-cyan/40 bg-cyan/10 px-3 py-1.5 text-sm font-medium text-cyan">
                        <flux:icon.check-circle variant="mini" /> Vous soutenez cette idée
                    </p>
                    <div class="mt-2">
                        <flux:button size="xs" variant="ghost" wire:click="retirerSoutien" wire:confirm="Retirer votre soutien à cette idée ?">Retirer mon soutien</flux:button>
                    </div>
                @elseif ($estAuteur)
                    <p class="text-sm text-ink-2">C’est votre idée : les autres habitants peuvent la soutenir.</p>
                @elseif (auth()->guest() && $record->estOuverte())
                    <flux:button variant="primary" icon="hand-thumb-up" :href="route('login')">Se connecter pour soutenir</flux:button>
                @elseif (auth()->check() && auth()->user()->can('soutenir', $record))
                    <flux:button variant="primary" icon="hand-thumb-up" wire:click="soutenir" wire:loading.attr="disabled">Je soutiens cette idée</flux:button>
                @else
                    <p class="text-sm text-ink-2">Cette idée ne peut plus être soutenue.</p>
                @endif
            </x-tn.surface>

            @if ($estAuteur)
                <x-tn.panel label="Suivi de votre idée">
                    <x-tn.timeline :items="$record->frise()" />
                </x-tn.panel>
            @endif
        </div>
    </div>
</section>
