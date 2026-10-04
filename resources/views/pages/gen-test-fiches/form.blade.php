<?php

use App\Models\GenTestFiche;
use App\Models\GenTestZone;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Gen Test Fiche')] class extends Component {
    #[Locked]
    public ?GenTestFiche $record = null;

    public string $titre = '';
    public string $description = '';
    public string $niveau = 'faible';
    public string $gen_test_zone_id = '';

    public function mount(?GenTestFiche $genTestFiche = null): void
    {
        if ($genTestFiche?->exists) {
            $this->authorize('update', $genTestFiche);
            $this->record = $genTestFiche;
            $this->titre = (string) ($genTestFiche->titre ?? '');
            $this->description = (string) ($genTestFiche->description ?? '');
            $this->niveau = (string) ($genTestFiche->niveau ?? '');
            $this->gen_test_zone_id = (string) ($genTestFiche->gen_test_zone_id ?? '');
        } else {
            $this->authorize('create', GenTestFiche::class);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'niveau' => ['required', Rule::in(GenTestFiche::NIVEAU_OPTIONS)],
            'gen_test_zone_id' => ['required', Rule::exists(GenTestZone::class, 'id')],
        ];
    }

    /**
     * Valeurs proposées dans la liste déroulante « Gen Test Zone ».
     *
     * @return Collection<int, GenTestZone>
     */
    #[Computed]
    public function genTestZoneOptions(): Collection
    {
        return GenTestZone::query()->orderBy('nom')->get(['id', 'nom']);
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', GenTestFiche::class);

        $validated = $this->validate();

        foreach (['description'] as $field) {
            if (($validated[$field] ?? null) === '') {
                $validated[$field] = null;
            }
        }

        if ($this->record) {
            $this->record->update($validated);
            $record = $this->record;
        } else {
            $record = new GenTestFiche($validated);
            $record->user()->associate(auth()->user());
            $record->save();
        }

        Flux::toast(variant: 'success', text: 'Gen Test Fiche enregistré(e).');

        $this->redirectRoute('gen-test-fiches.show', $record, navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:link :href="route('gen-test-fiches.index')" wire:navigate class="text-sm">&larr; Gen Test Fiches</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">
            {{ $record ? 'Modifier' : 'Ajouter' }} : Gen Test Fiche
        </flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="titre" label="Titre" required />

        <flux:textarea wire:model="description" label="Description" rows="5" />

        <flux:select wire:model="niveau" label="Niveau">
            @foreach (\App\Models\GenTestFiche::NIVEAU_OPTIONS as $option)
                <flux:select.option :value="$option">{{ ucfirst($option) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model="gen_test_zone_id" label="Gen Test Zone" placeholder="Choisir…" required>
            @foreach ($this->genTestZoneOptions as $option)
                <flux:select.option :value="$option->id">{{ $option->nom }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            <flux:button :href="route('gen-test-fiches.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
</section>
