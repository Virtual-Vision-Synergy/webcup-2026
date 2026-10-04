<?php

use App\Models\SecurityEvent;
use App\Services\FilSecurite;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
| F100 : détail d'un événement de sécurité (lecture seule, données masquées).
| {source} est limité par la route à FilSecurite::SOURCE_OPTIONS ; un id inconnu → 404.
*/
new #[Layout('layouts::agent'), Title('Sécurité — détail de l’événement')] class extends Component {
    /** @var array<string, mixed> */
    #[Locked]
    public array $evenement = [];

    public function mount(string $source, int $id): void
    {
        $this->authorize('consulterFil', SecurityEvent::class);

        $evenement = app(FilSecurite::class)->trouver($source, $id);

        abort_if($evenement === null, 404);

        $evenement['date'] = $evenement['date']->toIso8601String();
        $this->evenement = $evenement;
    }
}; ?>

@php
    $date = \Illuminate\Support\Carbon::parse($evenement['date'])->timezone(\App\Models\Annonce::FUSEAU);
@endphp

<section class="mx-auto w-full max-w-3xl space-y-6">
    <x-tn.page-header
        label="Espace agent"
        :title="$evenement['titre']"
        :subtitle="\App\Services\FilSecurite::SOURCE_OPTIONS[$evenement['source']]"
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Sécurité' => route('agent.securite.index'), 'Détail' => null]"
    />

    <x-tn.panel>
        <div class="space-y-5">
            <div class="flex flex-wrap items-center gap-2">
                <x-tn.status-badge :etat="\App\Services\FilSecurite::GRAVITE_ETATS[$evenement['gravite']]">
                    Gravité : {{ \App\Services\FilSecurite::GRAVITE_OPTIONS[$evenement['gravite']] }}
                </x-tn.status-badge>
            </div>

            <p class="text-lg text-ink" data-test="securite-phrase">{{ $evenement['phrase'] }}</p>

            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-ink-2">Date</dt>
                    <dd class="font-medium text-ink">{{ $date->format('d/m/Y à H:i:s') }} <span class="text-sm text-ink-2">(heure de Nova Terra)</span></dd>
                </div>
                <div>
                    <dt class="text-sm text-ink-2">Type</dt>
                    <dd class="font-medium text-ink">{{ \App\Services\FilSecurite::SOURCE_OPTIONS[$evenement['source']] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-ink-2">Compte concerné</dt>
                    <dd class="font-mono text-ink">{{ $evenement['compte'] ?? 'Aucun compte (visiteur non connecté)' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-ink-2">Adresse IP</dt>
                    <dd class="font-mono text-ink">{{ $evenement['ip'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-ink-2">Appareil</dt>
                    <dd class="text-ink">{{ $evenement['appareil'] ?? 'Inconnu' }}</dd>
                </div>
            </dl>
        </div>
    </x-tn.panel>

    <x-tn.surface padding="p-4" class="space-y-1">
        <x-tn.section-label class="text-xs!">Que faire ?</x-tn.section-label>
        <p class="text-sm text-ink">{{ $evenement['conseil'] }}</p>
    </x-tn.surface>

    <flux:text size="sm">
        Par confidentialité, l’adresse e-mail et l’adresse IP sont masquées. Le détail complet est réservé aux administrateurs.
    </flux:text>

    <flux:button :href="route('agent.securite.index')" variant="ghost" icon="arrow-left" wire:navigate>Retour aux événements</flux:button>
</section>
