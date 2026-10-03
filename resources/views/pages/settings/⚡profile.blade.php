<?php

use App\Concerns\ProfileValidationRules;
use App\Services\OnboardingProgress;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $telephone = '';
    public string $quartier = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->telephone = (string) Auth::user()->telephone;
        $this->quartier = (string) Auth::user()->quartier;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            ...$this->profileRules($user->id),
            'telephone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 .()-]{6,30}$/'],
            'quartier' => ['nullable', 'string', 'max:100'],
        ]);

        foreach (['telephone', 'quartier'] as $champ) {
            $validated[$champ] = trim($validated[$champ] ?? '') ?: null;
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));

        // Parcours de prise en main (D12) : retour à l'étape suivante si l'habitant vient de /bienvenue.
        if (OnboardingProgress::pour($user)->doitRevenirAuParcours()) {
            $this->redirectRoute('onboarding.show', navigate: true);
        }
    }

}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

            </div>

            <flux:input wire:model="telephone" label="Téléphone" type="tel" autocomplete="tel" placeholder="Ex. 034 12 345 67" />

            <flux:input wire:model="quartier" label="Quartier" type="text" autocomplete="address-level3" placeholder="Ex. Ambohitra" />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

            @can('view', \App\Models\Onboarding::class)
                <p class="mb-6 text-sm text-ink-2">
                    Nouveau à Nova Terra ?
                    <a href="{{ route('onboarding.show') }}" wire:navigate class="font-medium text-cyan hover:underline" data-test="revoir-onboarding">Revoir la prise en main</a>
                </p>
            @endcan

            <livewire:pages::settings.delete-user-form />
    </x-pages::settings.layout>
</section>
