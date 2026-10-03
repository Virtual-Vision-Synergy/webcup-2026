<?php

use App\Models\Service;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Service')] class extends Component {
    #[Locked]
    public ?Service $record = null;

    public string $nom = '';
    public string $description = '';
    public string $icone = '';
    public bool $mis_en_avant = false;

    public function mount(?Service $service = null): void
    {
        if ($service?->exists) {
            $this->authorize('update', $service);
            $this->record = $service;
            $this->nom = (string) ($service->nom ?? '');
            $this->description = (string) ($service->description ?? '');
            $this->icone = (string) ($service->icone ?? '');
            $this->mis_en_avant = (bool) $service->mis_en_avant;
        } else {
            $this->authorize('create', Service::class);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'icone' => ['required', 'string', 'max:255'],
            'mis_en_avant' => ['boolean'],
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Service::class);

        $validated = $this->validate();
        $miseEnAvant = (bool) ($validated['mis_en_avant'] ?? false);
        unset($validated['mis_en_avant']);

        $record = $this->record ?? new Service;
        $record->fill($validated);

        if (! $record->exists) {
            $record->user()->associate(auth()->user());
        }

        // Champ réservé : seuls les agents et admins peuvent le changer (sinon la valeur actuelle est conservée).
        if (auth()->user()->can('feature', $record)) {
            $record->mis_en_avant = $miseEnAvant;
        }

        $record->save();

        Cache::forget('landing.etat');

        Flux::toast(variant: 'success', text: 'Service enregistré(e).');

        $this->redirectRoute('services.show', $record, navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-2xl space-y-6">
    <x-tn.page-header
        label="Annuaire"
        :title="$record ? 'Modifier le service' : 'Ajouter un service'"
        :breadcrumb="['Services' => route('services.index'), ($record ? 'Modifier' : 'Nouveau') => null]"
    />

    <form wire:submit="save" class="space-y-6 rounded-md border border-line bg-surface p-5 md:p-6">
        <flux:input wire:model="nom" label="Nom" required />

        <flux:textarea wire:model="description" label="Description" rows="5" required />

        <flux:input wire:model="icone" label="Icone" required />

        @can('feature', $record ?? Service::class)
            <flux:checkbox wire:model="mis_en_avant" label="Mettre en avant" description="Le service apparaît en tête du catalogue et sur la page d'accueil." />
        @endcan

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            <flux:button :href="route('services.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
</section>
