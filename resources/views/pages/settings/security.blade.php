<?php

use App\Concerns\PasswordValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Attributes\On;

new #[Title('Security settings')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public bool $canManageTwoFactor;

    public bool $twoFactorEnabled;

    public bool $requiresConfirmation;

    /** F54 : message affiché après « Ce n'était pas moi » (autres appareils déconnectés). */
    public ?string $alerteAppareil = null;

    /** Agents et administrateurs : la vérification en deux étapes leur est fortement recommandée. */
    public bool $twoFactorRecommended = false;

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->alerteAppareil = session()->pull('appareil_signale');

        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
            $this->twoFactorRecommended = auth()->user()->isAgent() || auth()->user()->isAdmin();
        }

    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }


    /**
     * Handle the two-factor authentication enabled event.
     */
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;

        Flux::toast(variant: 'warning', text: 'Vérification en deux étapes désactivée : seul votre mot de passe protège désormais votre compte.');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Security settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        @if ($alerteAppareil)
            <flux:callout variant="warning" icon="shield-exclamation" :heading="$alerteAppareil" data-test="alerte-appareil" />
        @endif

        <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
            <flux:input
                wire:model="current_password"
                :label="__('Current password')"
                type="password"
                required
                autocomplete="current-password"
                viewable
            />
            <flux:input
                wire:model="password"
                :label="__('New password')"
                type="password"
                required
                autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />
            <flux:input
                wire:model="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" data-test="update-password-button">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>

        @if ($canManageTwoFactor)
            <section class="mt-12">
                <flux:heading>{{ __('Two-factor authentication') }}</flux:heading>
                <flux:subheading>{{ __('Manage your two-factor authentication settings') }}</flux:subheading>
                <x-tn.mots-utiles class="mt-2 mb-4" :slugs="['verification-deux-etapes', 'code-de-secours']" />
                <livewire:explication-simple cle="verification-deux-etapes" class="mb-4" />

                <div class="flex flex-col w-full mx-auto space-y-6 text-sm" wire:cloak>
                    @if ($twoFactorEnabled)
                        <div class="space-y-4">
                            <flux:callout variant="success" icon="shield-check" heading="Vérification en deux étapes active">
                                <flux:callout.text>
                                    À chaque connexion (mot de passe ou lien reçu par e-mail), un code à 6 chiffres affiché par votre application de vérification vous sera demandé. Sans ce code, personne ne peut entrer dans votre espace, même avec votre mot de passe.
                                </flux:callout.text>
                            </flux:callout>

                            <div class="flex justify-start">
                                <flux:button
                                    variant="danger"
                                    wire:click="disable"
                                >
                                    {{ __('Disable 2FA') }}
                                </flux:button>
                            </div>

                            <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                        </div>
                    @else
                        <div class="space-y-4">
                            @if ($twoFactorRecommended)
                                <flux:callout variant="warning" icon="exclamation-triangle" heading="Fortement recommandée pour votre compte">
                                    <flux:callout.text>
                                        En tant qu'agent ou administrateur, vous avez accès aux données des habitants. Activez la vérification en deux étapes pour qu'un mot de passe volé ne suffise pas à entrer dans votre compte.
                                    </flux:callout.text>
                                </flux:callout>
                            @endif

                            <flux:text variant="subtle">
                                La vérification en deux étapes ajoute une seconde vérification après votre mot de passe : un code à 6 chiffres qui change toutes les 30 secondes, affiché sur votre téléphone. Même si quelqu'un connaît votre mot de passe, il ne pourra pas accéder à votre espace.
                            </flux:text>

                            <ol class="list-decimal space-y-1 ps-5 text-zinc-600 dark:text-zinc-300">
                                <li>Installez une application de vérification gratuite sur votre téléphone (Google Authenticator, Microsoft Authenticator, FreeOTP…).</li>
                                <li>Cliquez sur « Activer la vérification en deux étapes » puis scannez le QR code avec cette application.</li>
                                <li>Saisissez le code à 6 chiffres affiché par l'application pour confirmer.</li>
                                <li>Téléchargez vos codes de secours et gardez-les en lieu sûr : ils vous dépannent si vous perdez votre téléphone.</li>
                            </ol>

                            <flux:modal.trigger name="two-factor-setup-modal">
                                <flux:button
                                    variant="primary"
                                    wire:click="$dispatch('start-two-factor-setup')"
                                >
                                    {{ __('Enable 2FA') }}
                                </flux:button>
                            </flux:modal.trigger>

                            <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                        </div>
                    @endif
                </div>
            </section>
        @endif

    </x-pages::settings.layout>

</section>
