<?php

use App\Models\GenTestZone;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Gen Test Zone')] class extends Component {
    #[Locked]
    public ?GenTestZone $record = null;

    public string $nom = '';

    public function mount(?GenTestZone $genTestZone = null): void
    {
        if ($genTestZone?->exists) {
            $this->authorize('update', $genTestZone);
            $this->record = $genTestZone;
            $this->nom = (string) ($genTestZone->nom ?? '');
        } else {
            $this->authorize('create', GenTestZone::class);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', GenTestZone::class);

        $validated = $this->validate();

        if ($this->record) {
            $this->record->update($validated);
            $record = $this->record;
        } else {
            $record = new GenTestZone($validated);
            $record->user()->associate(auth()->user());
            $record->save();
        }

        Flux::toast(variant: 'success', text: 'Gen Test Zone enregistré(e).');

        $this->redirectRoute('gen-test-zones.show', $record, navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:link :href="route('gen-test-zones.index')" wire:navigate class="text-sm">&larr; Gen Test Zones</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">
            {{ $record ? 'Modifier' : 'Ajouter' }} : Gen Test Zone
        </flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="nom" label="Nom" required />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            <flux:button :href="route('gen-test-zones.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
</section>
