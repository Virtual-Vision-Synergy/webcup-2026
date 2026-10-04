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
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" class="max-sm:h-auto! max-sm:flex-col">
            <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
        </flux:radio.group>

        <div class="mt-8">
            <flux:heading level="3">{{ __('Taille du texte') }}</flux:heading>
            <flux:text class="mb-3">{{ __('Agrandissez les caractères : le choix est mémorisé sur cet appareil.') }}</flux:text>
            <x-tn.text-size />
        </div>

        <div class="mt-8">
            <flux:heading level="3">{{ __('Langue') }}</flux:heading>
            <flux:text class="mb-3">{{ __('Langue d\'affichage du site.') }}</flux:text>
            <x-tn.langue />
            <flux:heading level="3">{{ __('Mode allégé') }}</flux:heading>
            <flux:text class="mb-3">
                {{ __('Connexion lente ? Le mode allégé retire les images décoratives, les effets de flou et les animations pour que les pages s\'affichent plus vite. Le choix est mémorisé sur votre compte.') }}
            </flux:text>
            <div class="flex items-center gap-3">
                <x-tn.mode-allege />
                <flux:text>{{ \App\Support\ModeAllege::actif() ? __('Activé') : __('Désactivé') }}</flux:text>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
