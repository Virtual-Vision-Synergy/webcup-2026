<?php

use App\Concerns\ProfileValidationRules;
use App\Models\Quartier;
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
    /** Quartier choisi dans la liste (F29) : sert au ciblage des alertes. */
    public string $quartier_id = '';
    /** F30 : recevoir les annonces urgentes par e-mail (préférence de l'habitant). */
    public bool $notifier_par_email = true;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->telephone = (string) Auth::user()->telephone;
        $this->quartier_id = (string) (Auth::user()->quartier_id ?? '');
        $this->notifier_par_email = (bool) Auth::user()->notifier_par_email;
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
            'quartier_id' => ['nullable', 'integer', 'exists:quartiers,id'],
            'notifier_par_email' => ['boolean'],
        ], [
            'quartier_id.integer' => 'Choisissez un quartier dans la liste.',
            'quartier_id.exists' => 'Choisissez un quartier dans la liste.',
        ]);

        $validated['telephone'] = trim($validated['telephone'] ?? '') ?: null;
        $validated['quartier_id'] = filled($validated['quartier_id'] ?? null) ? (int) $validated['quartier_id'] : null;
        // Ancienne saisie libre (D12) tenue à jour avec le nom du quartier choisi.
        $validated['quartier'] = $validated['quartier_id'] ? Quartier::query()->whereKey($validated['quartier_id'])->value('nom') : null;

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

            <flux:select wire:model="quartier_id" label="Quartier" description="Pour recevoir en priorité les alertes qui concernent votre quartier.">
                <flux:select.option value="">Non renseigné</flux:select.option>
                @foreach (\App\Models\Quartier::query()->orderBy('nom')->pluck('nom', 'id') as $id => $nom)
                    <flux:select.option :value="$id">{{ $nom }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:checkbox wire:model="notifier_par_email" label="Recevoir les annonces urgentes par e-mail"
                description="Les annonces de niveau Danger vous sont aussi envoyées par e-mail. Elles restent toujours visibles dans la cloche." />

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
