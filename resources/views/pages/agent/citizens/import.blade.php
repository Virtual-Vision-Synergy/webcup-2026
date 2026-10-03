<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\User;
use App\Services\ComptesHabitants;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts::agent'), Title('Import de comptes habitants')] class extends Component {
    use ThrottlesPerUser, WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $fichier = null;

    /** @var list<array{nom: string, identifiant: string, telephone: string|null, code: string}> */
    #[Locked]
    public array $fiches = [];

    /** @var list<string> */
    #[Locked]
    public array $erreurs = [];

    public function mount(): void
    {
        $this->authorize('createResidentAccounts', User::class);
    }

    public function import(ComptesHabitants $comptes): void
    {
        $this->authorize('createResidentAccounts', User::class);
        $this->throttlePerUser('import-habitants', maxAttempts: 5, decaySeconds: 60);

        $this->validate([
            'fichier' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ], [
            'fichier.required' => __('Choisissez un fichier CSV.'),
            'fichier.mimes' => __('Le fichier doit être au format CSV.'),
        ]);

        $resultat = $comptes->importer($this->fichier->getRealPath());

        $this->fiches = $resultat['crees'];
        $this->erreurs = $resultat['erreurs'];
        $this->reset('fichier');

        if ($this->fiches === []) {
            Flux::toast(variant: 'danger', text: __('Aucun compte créé : vérifiez le fichier.'));

            return;
        }

        Flux::toast(variant: 'success', text: __(':n compte(s) créé(s) : imprimez les fiches d\'activation.', ['n' => count($this->fiches)]));
    }
}; ?>

<section class="mx-auto w-full max-w-4xl space-y-6">
    <x-tn.page-header
        label="{{ __('Espace agent') }}"
        title="{{ __('Importer des comptes habitants') }}"
        :subtitle="__('Jusqu\'à :n habitants par fichier, sans adresse e-mail.', ['n' => ComptesHabitants::MAX_LIGNES_CSV])"
        :breadcrumb="['Espace agent' => route('agent.index'), 'Comptes citoyens' => route('agent.citizens.index'), 'Import CSV' => null]"
    >
        <x-slot:actions>
            <flux:button icon="user-plus" :href="route('agent.citizens.create')" wire:navigate>{{ __('Créer un seul compte') }}</flux:button>
        </x-slot:actions>
    </x-tn.page-header>

    <x-tn.surface>
        <form wire:submit="import" class="space-y-4">
            <flux:input type="file" wire:model="fichier" :label="__('Fichier CSV (1 Mo max)')" accept=".csv,text/csv,text/plain" />
            <div wire:loading wire:target="fichier"><flux:text>{{ __('Envoi en cours…') }}</flux:text></div>
            @error('throttle') <flux:text class="text-red-500">{{ $message }}</flux:text> @enderror

            <div class="rounded-sm border border-line p-3 text-sm text-ink-2">
                <p class="font-medium text-ink">{{ __('Format attendu (une ligne par habitant, en-tête facultatif) :') }}</p>
                <pre class="mt-2 overflow-x-auto font-mono text-xs">nom;telephone;langue
Rakoto Jean;034 12 345 67;mg
Amina Said;;fr
John Smith;+261 32 00 000 00;en</pre>
                <p class="mt-2">{{ __('Séparateur « ; » ou « , ». Téléphone et langue (fr, mg, en) facultatifs.') }}</p>
            </div>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="import, fichier">{{ __('Importer') }}</flux:button>
            </div>
            <div wire:loading wire:target="import" class="flex items-center gap-2 text-sm text-ink-2">
                <flux:icon.loading class="size-4" /> {{ __('Création des comptes…') }}
            </div>
        </form>
    </x-tn.surface>

    @if ($erreurs !== [])
        <x-tn.surface>
            <x-tn.section-label as="h2" class="mb-2">{{ __('Lignes ignorées') }} ({{ count($erreurs) }})</x-tn.section-label>
            <ul class="list-disc space-y-1 ps-5 text-sm text-ink-2">
                @foreach ($erreurs as $erreur)
                    <li>{{ $erreur }}</li>
                @endforeach
            </ul>
        </x-tn.surface>
    @endif

    @if ($fiches !== [])
        <x-habitants.fiches-activation :fiches="$fiches" />
    @endif
</section>
