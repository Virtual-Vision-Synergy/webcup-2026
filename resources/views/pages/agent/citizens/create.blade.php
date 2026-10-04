<?php

use App\Concerns\ThrottlesPerUser;
use App\Http\Middleware\DefinirLangue;
use App\Models\User;
use App\Services\ComptesHabitants;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::agent'), Title('Nouveau compte habitant')] class extends Component {
    use ThrottlesPerUser;

    public string $name = '';

    public string $telephone = '';

    public string $langue = 'fr';

    /** @var list<array{nom: string, identifiant: string, telephone: string|null, code: string}> Fiches créées pendant cette visite. */
    #[Locked]
    public array $fiches = [];

    public function mount(): void
    {
        $this->authorize('createResidentAccounts', User::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 .()-]{6,30}$/'],
            'langue' => ['required', Rule::in(array_keys(DefinirLangue::LANGUES))],
        ];
    }

    public function save(ComptesHabitants $comptes): void
    {
        $this->authorize('createResidentAccounts', User::class);
        $this->throttlePerUser('comptes-habitants', maxAttempts: 30, decaySeconds: 60);

        $validated = $this->validate();
        $telephone = User::normaliserTelephone($validated['telephone'] ?? null);

        if ($telephone !== null && User::where('telephone', $telephone)->exists()) {
            $this->addError('telephone', __('Ce numéro de téléphone est déjà utilisé.'));

            return;
        }

        ['user' => $user, 'code' => $code] = $comptes->creer([
            'name' => $validated['name'],
            'telephone' => $telephone,
            'langue' => $validated['langue'],
        ]);

        array_unshift($this->fiches, ['nom' => $user->name, 'identifiant' => (string) $user->identifiant, 'telephone' => $user->telephone, 'code' => $code]);

        $this->reset('name', 'telephone');
        Flux::toast(variant: 'success', text: __('Compte créé pour :nom : imprimez sa fiche d\'activation.', ['nom' => $user->name]));
    }
}; ?>

<section class="mx-auto w-full max-w-4xl space-y-6">
    <x-tn.page-header
        label="{{ __('Espace agent') }}"
        title="{{ __('Nouveau compte habitant') }}"
        :subtitle="__('Pour un habitant sans adresse e-mail : il recevra une fiche avec son identifiant et un code d\'activation à usage unique.')"
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Comptes citoyens' => route('agent.citizens.index'), 'Nouveau compte' => null]"
    >
        <x-slot:actions>
            <flux:button icon="arrow-up-tray" :href="route('agent.citizens.import')" wire:navigate>{{ __('Importer un fichier CSV') }}</flux:button>
        </x-slot:actions>
    </x-tn.page-header>

    <x-tn.surface>
        <form wire:submit="save" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:input wire:model="name" :label="__('Nom complet')" required autofocus class="sm:col-span-2" />
            <flux:input wire:model="telephone" :label="__('Téléphone (facultatif)')" type="tel" icon="phone" placeholder="034 12 345 67" :description="__('Permet aussi de se connecter avec ce numéro.')" />
            <flux:select wire:model="langue" :label="__('Langue de l\'habitant')">
                @foreach (DefinirLangue::LANGUES as $code => $libelle)
                    <flux:select.option value="{{ $code }}">{{ $libelle }}</flux:select.option>
                @endforeach
            </flux:select>
            @error('throttle') <flux:text class="text-magenta! sm:col-span-2">{{ $message }}</flux:text> @enderror
            <div class="flex justify-end sm:col-span-2">
                <flux:button type="submit" variant="primary" icon="user-plus" wire:loading.attr="disabled">{{ __('Créer le compte') }}</flux:button>
            </div>
        </form>
    </x-tn.surface>

    @if ($fiches !== [])
        <x-habitants.fiches-activation :fiches="$fiches" />
    @else
        <x-tn.empty icon="identification" title="{{ __('Aucune fiche pour le moment') }}" text="{{ __('Les fiches d\'activation des comptes créés apparaîtront ici, prêtes à imprimer.') }}" />
    @endif
</section>
