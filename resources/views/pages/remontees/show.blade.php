<?php

use App\Models\Remontee;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ma remontée')] class extends Component {
    #[Locked]
    public Remontee $record;

    public function mount(Remontee $remontee): void
    {
        $this->authorize('view', $remontee);
        $this->record = $remontee;
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        label="Remontée"
        :title="$record->objet"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Mes remontées' => route('concerns.index'), $record->reference => null]"
    >
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-ink-2">
                <x-tn.status-badge :etat="$record->etatStatut()">{{ Remontee::libelleStatut($record->statut) }}</x-tn.status-badge>
                <span class="font-mono text-xs">{{ $record->reference }}</span>
                <span>{{ Remontee::libelleCategorie($record->categorie) }}</span>
            </div>
        </x-slot:meta>
    </x-tn.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <div class="flex flex-col gap-6">
            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-2">Votre message</x-tn.section-label>
                <p class="whitespace-pre-line leading-relaxed text-ink" data-test="message">{{ $record->message }}</p>
                <p class="mt-3 font-mono text-xs text-ink-2">Envoyé le {{ Remontee::dateLocale($record->envoyee_le) }}</p>
            </x-tn.surface>

            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-2">Réponse de la mairie</x-tn.section-label>
                @if ($record->reponse !== null)
                    <p class="whitespace-pre-line leading-relaxed text-ink" data-test="reponse">{{ $record->reponse }}</p>
                    <p class="mt-3 font-mono text-xs text-ink-2">Réponse du service municipal le {{ Remontee::dateLocale($record->repondue_le) }}</p>
                @else
                    <p class="text-ink-2">Pas encore de réponse. Vous serez prévenu dès qu’un agent vous aura répondu.</p>
                @endif
            </x-tn.surface>
        </div>

        <x-tn.panel label="Suivi">
            <x-tn.timeline :items="$record->frise()" />
        </x-tn.panel>
    </div>
</section>
