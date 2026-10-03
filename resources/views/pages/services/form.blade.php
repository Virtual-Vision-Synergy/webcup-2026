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
    public string $icone = '';

    public function mount(?Service $service = null): void
    {
        if ($service?->exists) {
            $this->authorize('update', $service);
            $this->record = $service;
            $this->nom = (string) ($service->nom ?? '');
            $this->description = (string) ($service->description ?? '');
            $this->icone = (string) ($service->icone ?? '');
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

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:link :href="route('services.index')" wire:navigate class="text-sm">&larr; Services</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">
            {{ $record ? 'Modifier' : 'Ajouter' }} : Service
        </flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="nom" label="Nom" required />

        <flux:textarea wire:model="description" label="Description" rows="5" required />

        <flux:input wire:model="icone" label="Icone" required />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            <flux:button :href="route('services.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
</section>
