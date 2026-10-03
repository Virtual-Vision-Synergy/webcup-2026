<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
        </flux:radio.group>

        {{-- Aides contextuelles (F35) : préférence propre à ce navigateur. --}}
        <div x-data class="mt-8 flex items-start justify-between gap-4 border-t border-line pt-6">
            <div>
                <flux:heading id="libelle-aides">Aides contextuelles</flux:heading>
                <flux:text id="description-aides" class="mt-1">Petites indications affichées sur les écrans clés. Désactivez-les quand vous connaissez la plateforme.</flux:text>
            </div>
            <button
                type="button"
                role="switch"
                x-bind:aria-checked="$store.aides.actives ? 'true' : 'false'"
                x-on:click="$store.aides.basculer(! $store.aides.actives)"
                aria-labelledby="libelle-aides"
                aria-describedby="description-aides"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border border-line transition-colors focus-visible:outline-2 focus-visible:outline-cyan"
                x-bind:class="$store.aides.actives ? 'bg-cyan' : 'bg-line'"
            >
                <span class="inline-block size-4 rounded-full bg-white shadow transition-transform" x-bind:class="$store.aides.actives ? 'translate-x-6' : 'translate-x-1'"></span>
            </button>
        </div>
    </x-pages::settings.layout>
</section>
