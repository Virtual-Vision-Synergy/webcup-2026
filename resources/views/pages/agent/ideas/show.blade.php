<?php

use App\Models\Idea;
use App\Notifications\Avis;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::agent'), Title('Espace agent — Idée')] class extends Component {
    #[Locked]
    public Idea $record;

    public string $status = '';

    public string $response = '';

    public function mount(Idea $idea): void
    {
        Gate::authorize('viewAgentSpace');
        $this->authorize('updateStatus', $idea);
        $this->record = $idea->loadMissing(['user', 'respondedBy']);
        $this->status = $idea->status;
        $this->response = (string) $idea->response;
    }

    /**
     * Change l'état et, le cas échéant, publie la réponse de la ville (obligatoire pour Retenue / Non retenue).
     */
    public function enregistrer(): void
    {
        $this->authorize('updateStatus', $this->record);

        $decision = in_array($this->status, Idea::STATUTS_DECISION, true);

        if ($decision || trim($this->response) !== '') {
            $this->authorize('respond', $this->record);
        }

        $this->validate(
            [
                'status' => ['required', Rule::in(Idea::STATUS_OPTIONS)],
                'response' => [$decision ? 'required' : 'nullable', 'string', 'min:10', 'max:3000'],
            ],
            [
                'status.required' => 'Choisissez un état.',
                'status.in' => 'Choisissez un état dans la liste.',
                'response.required' => 'Rédigez la réponse de la ville pour retenir ou non cette idée.',
                'response.min' => 'La réponse doit faire au moins :min caractères.',
                'response.max' => 'La réponse ne doit pas dépasser :max caractères.',
            ],
        );

        $ancienStatut = $this->record->status;
        $ancienneReponse = $this->record->response;

        $this->record->changerStatut(auth()->user(), $this->status, $this->response);
        $this->record->refresh()->load(['user', 'respondedBy']);

        if ($ancienStatut === $this->record->status && $ancienneReponse === $this->record->response) {
            Flux::toast(text: 'Aucun changement.');

            return;
        }

        $lignes = ['Votre idée « '.$this->record->title.' » ('.$this->record->reference.') est maintenant : '.Idea::libelleStatut($this->record->status).'.'];
        if ($this->record->response !== null && $ancienneReponse !== $this->record->response) {
            $lignes[] = 'La ville vous a répondu : consultez sa réponse sur la fiche de votre idée.';
        }

        // Compte supprimé : plus personne à prévenir.
        $this->record->user?->notify(new Avis(
            'Idée '.$this->record->reference.' : '.mb_strtolower(Idea::libelleStatut($this->record->status)),
            $lignes,
            'Voir mon idée',
            route('ideas.show', $this->record),
        ));

        Flux::toast(variant: 'success', text: 'Idée mise à jour. L’habitant est prévenu.');
    }

    public function masquer(): void
    {
        $this->authorize('hide', $this->record);

        $this->record->masquer();

        Flux::toast(text: 'Idée masquée : elle n’apparaît plus sur la page publique.');
    }

    public function reafficher(): void
    {
        $this->authorize('hide', $this->record);

        $this->record->reafficher();

        Flux::toast(variant: 'success', text: 'Idée de nouveau visible sur la page publique.');
    }
}; ?>

@php
    $nombreSoutiens = $record->soutiens()->count();
@endphp

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        label="Espace agent"
        :title="$record->title"
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Boîte à idées' => route('agent.ideas.index'), $record->reference => null]"
    >
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-ink-2">
                <x-tn.status-badge :etat="$record->etatStatut()">{{ Idea::libelleStatut($record->status) }}</x-tn.status-badge>
                <span class="font-mono text-xs">{{ $record->reference }}</span>
                <span>{{ Idea::libelleCategorie($record->category) }}</span>
                <span class="inline-flex items-center gap-1"><flux:icon.users variant="micro" /> {{ $nombreSoutiens }} soutien(s)</span>
                @if ($record->estMasquee())
                    <x-tn.status-badge etat="alerte">Masquée le {{ Idea::dateLocale($record->hidden_at) }}</x-tn.status-badge>
                @endif
            </div>
        </x-slot:meta>
    </x-tn.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <div class="flex flex-col gap-6">
            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-2">L’idée</x-tn.section-label>
                <p class="whitespace-pre-line leading-relaxed text-ink">{{ $record->description }}</p>
                <p class="mt-3 font-mono text-xs text-ink-2">Proposée par {{ $record->user?->name ?? 'un compte supprimé' }} le {{ Idea::dateLocale($record->created_at) }}</p>
            </x-tn.surface>

            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-3">État et réponse de la ville</x-tn.section-label>
                <form wire:submit="enregistrer" class="space-y-4">
                    <flux:select wire:model.live="status" label="État">
                        @foreach (Idea::STATUS_LABELS as $valeur => $libelle)
                            <flux:select.option :value="$valeur">{{ $libelle }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:textarea
                        wire:model="response"
                        label="Réponse de la ville"
                        :description="in_array($status, Idea::STATUTS_DECISION, true) ? 'Obligatoire : expliquez pourquoi l’idée est retenue ou non. L’habitant la lira sur la fiche publique de l’idée.' : 'Facultative à ce stade. Elle sera visible sur la fiche publique de l’idée.'"
                        rows="5"
                        maxlength="3000"
                    />
                    <flux:button type="submit" variant="primary" icon="check">
                        <span wire:loading.remove wire:target="enregistrer">Enregistrer et prévenir l’habitant</span>
                        <span wire:loading wire:target="enregistrer">Enregistrement…</span>
                    </flux:button>
                </form>
                @if ($record->responded_at)
                    <p class="mt-3 font-mono text-xs text-ink-2">Dernière réponse par {{ $record->respondedBy?->name ?? 'un agent' }} le {{ Idea::dateLocale($record->responded_at) }}</p>
                @endif
            </x-tn.surface>
        </div>

        <div class="flex flex-col gap-6">
            <x-tn.panel label="Suivi">
                <x-tn.timeline :items="$record->frise()" />
            </x-tn.panel>

            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-3">Modération</x-tn.section-label>
                @if ($record->estMasquee())
                    <flux:button wire:click="reafficher" icon="eye">Réafficher l’idée</flux:button>
                @else
                    <p class="mb-3 text-sm text-ink-2">Une idée inappropriée peut être masquée : elle disparaît de la page publique, son auteur la voit toujours.</p>
                    <flux:button wire:click="masquer" wire:confirm="Masquer cette idée de la page publique ?" icon="eye-slash">Masquer l’idée</flux:button>
                @endif
            </x-tn.surface>
        </div>
    </div>

    <x-audit-history :subject="$record" />
</section>
