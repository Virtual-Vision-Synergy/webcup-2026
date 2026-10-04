<?php

use App\Models\Remontee;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Remontée bien reçue')] class extends Component {
    #[Locked]
    public Remontee $record;

    public function mount(Remontee $remontee): void
    {
        $this->authorize('view', $remontee);
        $this->record = $remontee;
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6">
    <x-tn.page-header
        label="Accusé de réception"
        title="Votre remontée est bien reçue"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Mes remontées' => route('concerns.index'), $record->reference => null]"
    />

    <x-tn.panel label="Votre numéro de suivi">
        <p class="tn-display text-3xl font-semibold tracking-wide text-cyan" data-test="reference">{{ $record->reference }}</p>
        <p class="mt-2 text-ink-2">Notez-le : il permet de retrouver votre remontée si vous contactez la mairie.</p>

        <dl class="mt-4">
            <x-tn.field label="Envoyée le">{{ Remontee::dateLocale($record->envoyee_le) }}</x-tn.field>
            <x-tn.field label="Sujet">{{ Remontee::libelleCategorie($record->categorie) }}</x-tn.field>
            <x-tn.field label="Objet">{{ $record->objet }}</x-tn.field>
            <x-tn.field label="État"><x-tn.status-badge :etat="$record->etatStatut()">{{ Remontee::libelleStatut($record->statut) }}</x-tn.status-badge></x-tn.field>
        </dl>
    </x-tn.panel>

    <x-tn.surface>
        <x-tn.section-label as="h2" class="mb-3">Et maintenant ?</x-tn.section-label>
        <ol class="list-decimal space-y-2 ps-5 text-ink">
            <li>Un agent municipal lit votre message et le <strong>prend en compte</strong>.</li>
            <li>Il vous <strong>répond</strong> par écrit, dans votre espace.</li>
            <li>La remontée est ensuite <strong>clôturée</strong>.</li>
        </ol>
        <p class="mt-3 text-sm text-ink-2">À chaque étape, vous recevez un avis dans la cloche et un e-mail. Un avis de réception vient de vous être envoyé.</p>
    </x-tn.surface>

    <div class="flex flex-wrap gap-3">
        <flux:button variant="primary" icon="eye" :href="route('concerns.show', $record)" wire:navigate>Suivre ma remontée</flux:button>
        <flux:button icon="list-bullet" :href="route('concerns.index')" wire:navigate>Toutes mes remontées</flux:button>
    </div>
</section>
