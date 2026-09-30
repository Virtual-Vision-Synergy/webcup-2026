<?php

use App\Models\Signalement;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Signalement')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public ?Signalement $record = null;

    public string $titre = '';
    public string $description = '';
    public string $niveau = 'faible';
    public string $zone = '';
    public $photo = null;
    public string $date_incident = '';
    public string $latitude = '';
    public string $longitude = '';

    public function mount(?Signalement $signalement = null): void
    {
        if ($signalement?->exists) {
            $this->authorize('update', $signalement);
            $this->record = $signalement;
            $this->titre = (string) ($signalement->titre ?? '');
            $this->description = (string) ($signalement->description ?? '');
            $this->niveau = (string) ($signalement->niveau ?? '');
            $this->zone = (string) ($signalement->zone ?? '');
            $this->date_incident = $signalement->date_incident?->format('Y-m-d') ?? '';
            $this->latitude = (string) ($signalement->latitude ?? '');
            $this->longitude = (string) ($signalement->longitude ?? '');
        } else {
            $this->authorize('create', Signalement::class);
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
            'niveau' => ['required', Rule::in(Signalement::NIVEAU_OPTIONS)],
            'zone' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'date_incident' => ['required', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-180,180'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Signalement::class);

        $validated = $this->validate();

        foreach (['description', 'latitude', 'longitude'] as $field) {
            if (($validated[$field] ?? null) === '') {
                $validated[$field] = null;
            }
        }

        if ($this->photo) {
            if ($this->record?->photo) {
                Storage::disk('public')->delete($this->record->photo);
            }
            $validated['photo'] = $this->photo->store('signalements', 'public');
        } else {
            unset($validated['photo']);
        }

        if ($this->record) {
            $this->record->update($validated);
            $record = $this->record;
        } else {
            $record = new Signalement($validated);
            $record->user()->associate(auth()->user());
            $record->save();
        }

        Flux::toast(variant: 'success', text: 'Signalement enregistré(e).');

        $this->redirectRoute('signalements.show', $record, navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:link :href="route('signalements.index')" wire:navigate class="text-sm">&larr; Signalements</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">
            {{ $record ? 'Modifier' : 'Ajouter' }} : Signalement
        </flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="titre" label="Titre" required />

        <flux:textarea wire:model="description" label="Description" rows="5" />

        <flux:select wire:model="niveau" label="Niveau">
            @foreach (\App\Models\Signalement::NIVEAU_OPTIONS as $option)
                <flux:select.option :value="$option">{{ ucfirst($option) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="zone" label="Zone" required />

        <div class="space-y-3">
            <flux:input type="file" wire:model="photo" label="Photo" accept="image/jpeg,image/png,image/webp" />
            <div wire:loading wire:target="photo"><flux:text>Envoi en cours…</flux:text></div>
            @if ($photo)
                <img src="{{ $photo->temporaryUrl() }}" alt="Aperçu" class="h-40 rounded-lg object-cover" />
            @elseif ($record?->photo)
                <img src="{{ Storage::url($record->photo) }}" alt="Photo" class="h-40 rounded-lg object-cover" />
            @endif
        </div>

        <flux:input wire:model="date_incident" label="Date incident" type="date" required />

        <flux:input wire:model="latitude" label="Latitude" type="number" step="any" />

        <flux:input wire:model="longitude" label="Longitude" type="number" step="any" />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            <flux:button :href="route('signalements.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
</section>
