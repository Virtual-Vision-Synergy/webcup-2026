<?php

use App\Models\Service;
use Flux\Flux;
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
    public string $categorie = '';

    public function mount(?Service $service = null): void
    {
        if ($service?->exists) {
            $this->authorize('update', $service);
            $this->record = $service;
            $this->nom = (string) ($service->nom ?? '');
            $this->description = (string) ($service->description ?? '');
            $this->categorie = (string) ($service->categorie ?? '');
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
            'categorie' => ['required', Rule::in(Service::CATEGORIE_OPTIONS)],
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Service::class);

        $validated = $this->validate();

        if ($this->record) {
            $this->record->update($validated);
            $record = $this->record;
        } else {
            $record = new Service($validated);
            $record->user()->associate(auth()->user());
            $record->save();
        }

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

        <flux:select wire:model="categorie" label="Catégorie" placeholder="Choisir une catégorie…" required>
            @foreach (Service::CATEGORIE_LABELS as $valeur => $label)
                <flux:select.option value="{{ $valeur }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            <flux:button :href="route('services.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
</section>
