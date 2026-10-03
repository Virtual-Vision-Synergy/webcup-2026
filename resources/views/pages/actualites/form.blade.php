<?php

use App\Models\Actualite;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Actualite')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public ?Actualite $record = null;

    public string $titre = '';
    public string $contenu = '';
    public string $date = '';
    public $image = null;

    public function mount(?Actualite $actualite = null): void
    {
        if ($actualite?->exists) {
            $this->authorize('update', $actualite);
            $this->record = $actualite;
            $this->titre = (string) ($actualite->titre ?? '');
            $this->contenu = (string) ($actualite->contenu ?? '');
            $this->date = $actualite->date?->format('Y-m-d') ?? '';
        } else {
            $this->authorize('create', Actualite::class);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'contenu' => ['required', 'string', 'max:5000'],
            'date' => ['required', 'date'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Actualite::class);

        $validated = $this->validate();

        if ($this->image) {
            if ($this->record?->image) {
                Storage::disk('public')->delete($this->record->image);
            }
            $validated['image'] = $this->image->store('actualites', 'public');
        } else {
            unset($validated['image']);
        }

        if ($this->record) {
            $this->record->update($validated);
            $record = $this->record;
        } else {
            $record = new Actualite($validated);
            $record->user()->associate(auth()->user());
            $record->save();
        }

        Flux::toast(variant: 'success', text: 'Actualite enregistré(e).');

        $this->redirectRoute('actualites.show', $record, navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:link :href="route('actualites.index')" wire:navigate class="text-sm">&larr; Actualites</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">
            {{ $record ? 'Modifier' : 'Ajouter' }} : Actualite
        </flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="titre" label="Titre" required />

        <flux:textarea wire:model="contenu" label="Contenu" rows="5" required />

        <flux:input wire:model="date" label="Date" type="date" required />

        <div class="space-y-3">
            <flux:input type="file" wire:model="image" label="Image" accept="image/jpeg,image/png,image/webp" />
            <div wire:loading wire:target="image"><flux:text>Envoi en cours…</flux:text></div>
            @if ($image)
                <img src="{{ $image->temporaryUrl() }}" alt="Aperçu" class="h-40 rounded-lg object-cover" />
            @elseif ($record?->image)
                <img src="{{ Storage::url($record->image) }}" alt="Image" class="h-40 rounded-lg object-cover" />
            @endif
        </div>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            <flux:button :href="route('actualites.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
</section>
