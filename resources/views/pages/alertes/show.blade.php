<?php

use App\Models\Annonce;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Page publique d'une alerte (F29), décision assumée : l'information de sécurité se consulte sans compte.
 * Seules les annonces en cours de diffusion sont visibles (AnnoncePolicy::view, 404 sinon).
 */
new #[Layout('layouts::public'), Title('Alerte')] class extends Component {
    #[Locked]
    public Annonce $record;

    public function mount(Annonce $annonce): void
    {
        $this->authorize('view', $annonce);
        $this->record = $annonce->load('quartier:id,nom');
    }
}; ?>

@php
    $concerne = $record->concerne(auth()->user());
    $etat = match ($record->niveau) { 'danger', 'alerte' => 'alerte', 'vigilance' => 'perturbe', default => 'info' };
@endphp

<section class="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 lg:px-8">
    <x-tn.page-header
        :label="$record->estOfficiel() ? 'Message officiel du Haut Conseil' : 'Alerte en cours'"
        :title="$record->titre"
        :subtitle="$record->estCiblee() ? 'Quartier '.$record->nomQuartier().' uniquement' : 'Toute la ville de Nova Terra'"
    >
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <x-tn.status-badge :etat="$etat" live>{{ $record->libelleNiveau() }}</x-tn.status-badge>
                @if ($concerne)
                    <x-tn.status-badge etat="alerte">Concerne votre quartier : {{ $record->nomQuartier() }}</x-tn.status-badge>
                @endif
            </div>
            <x-tn.mots-utiles class="mt-3" :slugs="['alerte', 'quartier']" />
        </x-slot:meta>
    </x-tn.page-header>

    {{-- F89 : version simple en langage clair (relue par la mairie, sinon simplifiée automatiquement). --}}
    <x-tn.langage-clair :version="\App\Support\LangageClair::pourAnnonce($record)">
        <x-tn.bandeau-annonce
            class="rounded-md border"
            variante="renforce"
            :officiel="$record->estOfficiel()"
            :date="$record->debut"
            :impact="$record->impact_prevu_le"
            :fin="$record->fin"
            :niveau="$record->niveau"
            :titre="$record->titre"
            :contenu="$record->contenu"
            :consignes="$record->listeConsignes()"
            :quartier="$concerne ? $record->nomQuartier() : null"
        />
    </x-tn.langage-clair>

    <dl class="grid gap-4 rounded-md border border-line bg-surface p-5 text-sm sm:grid-cols-2">
        <div>
            <dt class="text-ink-2">En vigueur depuis</dt>
            <dd class="font-medium text-ink">{{ $record->debut->timezone(\App\Models\Annonce::FUSEAU)->translatedFormat('d F Y à H\hi') }}</dd>
        </div>
        <div>
            <dt class="text-ink-2">Jusqu’au</dt>
            <dd class="font-medium text-ink">{{ $record->fin->timezone(\App\Models\Annonce::FUSEAU)->translatedFormat('d F Y à H\hi') }}</dd>
        </div>
        @if ($record->impact_prevu_le)
            <div class="sm:col-span-2">
                <dt class="text-ink-2">Début estimé de la perturbation</dt>
                <dd class="font-medium text-ink">{{ $record->impact_prevu_le->timezone(\App\Models\Annonce::FUSEAU)->translatedFormat('d F Y à H\hi') }}</dd>
            </div>
        @endif
        <div class="sm:col-span-2">
            <dt class="text-ink-2">Dernière mise à jour</dt>
            <dd class="font-medium text-ink">{{ $record->updated_at?->timezone(\App\Models\Annonce::FUSEAU)->translatedFormat('d F Y à H\hi') }} (heure de Madagascar)</dd>
        </div>
    </dl>

    <div class="flex flex-wrap gap-3">
        <flux:button :href="route('home')" variant="ghost" icon="arrow-left">Accueil</flux:button>
        @auth
            @if (auth()->user()->quartier_id === null)
                <flux:button :href="route('profile.edit')" variant="primary" icon="map-pin">Indiquer mon quartier</flux:button>
            @endif
        @endauth
    </div>
</section>
