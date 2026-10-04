<?php

use App\Models\Idea;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Idée bien reçue')] class extends Component {
    #[Locked]
    public Idea $record;

    public function mount(Idea $idea): void
    {
        $this->authorize('viewOwn', $idea);
        $this->record = $idea;
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6">
    <x-tn.page-header
        label="Accusé de réception"
        title="Merci, votre idée est bien reçue"
        :breadcrumb="['Boîte à idées' => route('ideas.index'), 'Mes idées' => route('ideas.mine'), $record->reference => null]"
    />

    <x-tn.panel label="Votre numéro de suivi">
        <p class="tn-display text-3xl font-semibold tracking-wide text-cyan" data-test="reference">{{ $record->reference }}</p>
        <p class="mt-2 text-ink-2">Notez-le : il permet de retrouver votre idée à tout moment.</p>

        <dl class="mt-4">
            <x-tn.field label="Envoyée le">{{ Idea::dateLocale($record->created_at) }}</x-tn.field>
            <x-tn.field label="Catégorie">{{ Idea::libelleCategorie($record->category) }}</x-tn.field>
            <x-tn.field label="Titre">{{ $record->title }}</x-tn.field>
            <x-tn.field label="État"><x-tn.status-badge :etat="$record->etatStatut()">{{ Idea::libelleStatut($record->status) }}</x-tn.status-badge></x-tn.field>
        </dl>
    </x-tn.panel>

    <x-tn.surface>
        <x-tn.section-label as="h2" class="mb-3">Et maintenant ?</x-tn.section-label>
        <ol class="list-decimal space-y-2 ps-5 text-ink">
            <li>Votre idée est <strong>publiée</strong> : les autres habitants peuvent la soutenir.</li>
            <li>Un agent l’<strong>étudiera</strong>.</li>
            <li>Vous recevrez sa <strong>réponse</strong> (retenue ou non) ici et dans vos avis.</li>
        </ol>
        <p class="mt-3 text-sm text-ink-2">Un avis de réception vient de vous être envoyé.</p>
    </x-tn.surface>

    <div class="flex flex-wrap gap-3">
        <flux:button variant="primary" icon="eye" :href="route('ideas.show', $record)" wire:navigate>Voir mon idée</flux:button>
        <flux:button icon="list-bullet" :href="route('ideas.mine')" wire:navigate>Toutes mes idées</flux:button>
    </div>
</section>
