<?php

use App\Models\Actualite;
use App\Services\OptimiseurImage;
use Flux\Flux;
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
            $optimiseur = app(OptimiseurImage::class);
            $optimiseur->supprimer($this->record?->image);
            // F60 : redimensionnée (1600 px max) et compressée en WebP à l'enregistrement.
            $validated['image'] = $optimiseur->enregistrer($this->image, 'actualites');
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

<section class="mx-auto w-full max-w-2xl space-y-6">
    <x-tn.page-header
        label="Fil du Haut Conseil"
        :title="$record ? 'Modifier l’annonce' : 'Publier une annonce'"
        :breadcrumb="$record
            ? ['Mon espace' => route('dashboard'), 'Actualités' => route('actualites.index'), ($record->titre ?: 'Annonce') => route('actualites.show', $record), 'Modifier' => null]
            : ['Mon espace' => route('dashboard'), 'Actualités' => route('actualites.index'), 'Nouveau' => null]"
    />

    <form wire:submit="save" class="space-y-6 rounded-md border border-line bg-surface p-5 md:p-6">
        <flux:input wire:model="titre" label="Titre" required />

        <flux:textarea wire:model="contenu" label="Contenu" rows="5" required />

        <flux:input wire:model="date" label="Date" type="date" required />

        <div class="space-y-3">
            <flux:input type="file" wire:model="image" label="Image" accept="image/jpeg,image/png,image/webp" />
            <div wire:loading wire:target="image"><flux:text>Envoi en cours…</flux:text></div>
            @if ($image)
                <img src="{{ $image->temporaryUrl() }}" alt="Aperçu" class="h-40 rounded-lg object-cover" />
            @elseif ($record?->image)
                <x-tn.image :chemin="$record->image" alt="Image actuelle de l'annonce" sizes="320px" class="h-40 w-auto rounded-lg object-cover" />
            @endif
        </div>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            <flux:button :href="route('actualites.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
</section>
