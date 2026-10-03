<?php

use App\Concerns\PasswordValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Computed;
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

    /**
     * Agents et administrateurs : la double authentification leur est fortement recommandée.
     */
    #[Computed]
    public function compteSensible(): bool
    {
        $user = auth()->user();

        return $user->isAgent() || $user->isAdmin();
    }

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
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
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Security settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
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
            <section class="mt-12" id="double-authentification">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading>{{ __('Two-factor authentication') }}</flux:heading>
                    @if ($twoFactorEnabled)
                        <flux:badge color="green" size="sm" icon="shield-check">Activée</flux:badge>
                    @else
                        <flux:badge color="zinc" size="sm">Désactivée</flux:badge>
                    @endif
                </div>
                <flux:subheading>{{ __('Manage your two-factor authentication settings') }}</flux:subheading>

                <div class="flex flex-col w-full mx-auto mt-4 space-y-6 text-sm" wire:cloak>
                    @if (! $twoFactorEnabled && $this->compteSensible)
                        <flux:callout variant="warning" icon="shield-exclamation" heading="Fortement recommandée pour votre compte">
                            <flux:callout.text>
                                Votre compte {{ auth()->user()->role->label }} donne accès aux données d'autres habitants. Activez la double authentification pour qu'un mot de passe volé ne suffise pas à y entrer.
                            </flux:callout.text>
                        </flux:callout>
                    @endif

                    @if ($twoFactorEnabled)
                        <div class="space-y-4">
                            <flux:text>
                                {{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                            </flux:text>

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
                            <flux:text variant="subtle">
                                {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                            </flux:text>

                            <div class="rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                                <flux:heading size="sm" level="3">Comment ça marche ?</flux:heading>
                                <ol class="mt-3 list-decimal space-y-2 ps-5 text-zinc-600 dark:text-zinc-300">
                                    <li>Installez sur votre téléphone une application d'authentification gratuite : Google Authenticator, Microsoft Authenticator ou FreeOTP.</li>
                                    <li>Cliquez sur « Activer la 2FA », puis scannez le QR code avec l'application (ou recopiez la clé affichée en dessous).</li>
                                    <li>Saisissez le code à 6 chiffres affiché par l'application pour confirmer. Il change toutes les 30 secondes.</li>
                                    <li>Téléchargez vos codes de récupération et gardez-les en lieu sûr : ils vous dépannent si vous perdez votre téléphone.</li>
                                </ol>
                                <flux:text variant="subtle" class="mt-3">
                                    Ensuite, à chaque connexion, ce code vous sera demandé après votre mot de passe (ou après le lien de connexion reçu par e-mail). Sans lui, personne ne peut entrer dans votre espace.
                                </flux:text>
                            </div>

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
