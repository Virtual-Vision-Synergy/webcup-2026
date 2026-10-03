<?php

use App\Concerns\ProfileValidationRules;
use App\Models\Quartier;
use App\Models\User;
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
        // F71 : un compte sans e-mail n'affiche pas son adresse technique.
        $this->email = Auth::user()->emailAffichable() ?? '';
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
            // F71 : l'e-mail reste facultatif pour un compte créé sans e-mail.
            ...($user->aUnEmail() ? [] : ['email' => ['nullable', ...array_slice($this->emailRules($user->id), 1)]]),
            'telephone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 .()-]{6,30}$/'],
            'quartier_id' => ['nullable', 'integer', 'exists:quartiers,id'],
            'notifier_par_email' => ['boolean'],
        ], [
            'quartier_id.integer' => 'Choisissez un quartier dans la liste.',
            'quartier_id.exists' => 'Choisissez un quartier dans la liste.',
        ]);

        // F71 : téléphone enregistré sans espaces pour pouvoir se connecter avec.
        $validated['telephone'] = User::normaliserTelephone($validated['telephone'] ?? null);
        $validated['quartier_id'] = filled($validated['quartier_id'] ?? null) ? (int) $validated['quartier_id'] : null;
        // Ancienne saisie libre (D12) tenue à jour avec le nom du quartier choisi.
        $validated['quartier'] = $validated['quartier_id'] ? Quartier::query()->whereKey($validated['quartier_id'])->value('nom') : null;

        if (blank($validated['email'] ?? null)) {
            unset($validated['email']);
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
                <flux:input wire:model="email" :label="__('Email')" type="email" :required="auth()->user()->aUnEmail()" autocomplete="email" />

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

            <p class="mb-6 text-sm text-ink-2">
                Ce que la plateforme garde sur vous et ce que la suppression efface :
                <a href="{{ route('privacy.show') }}" wire:navigate class="font-medium text-cyan hover:underline" data-test="lien-vos-donnees">Vos données</a>
                · <a href="{{ route('concerns.index') }}" wire:navigate class="font-medium text-cyan hover:underline">Mes remontées</a>
            </p>

            <div class="mb-6 flex flex-wrap items-center gap-3">
                <flux:button icon="arrow-down-tray" :href="route('profile.data')" data-test="telecharger-mes-donnees">
                    Télécharger mes données
                </flux:button>
                <flux:text class="text-sm">Document lisible (PDF) et fichiers JSON / CSV.</flux:text>
            </div>

            <livewire:pages::settings.delete-user-form />
    </x-pages::settings.layout>
</section>
