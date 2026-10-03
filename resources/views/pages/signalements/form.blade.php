<?php

use App\Models\Signalement;
use App\Services\OptimiseurImage;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Signalement')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public ?Signalement $record = null;

    public string $categorie = '';
    public string $description = '';
    public string $lieu = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $photo = null;

    public function mount(?Signalement $signalement = null): void
    {
        if ($signalement?->exists) {
            $this->authorize('update', $signalement);
            $this->record = $signalement;
            $this->categorie = (string) $signalement->categorie;
            $this->description = (string) $signalement->description;
            $this->lieu = (string) $signalement->lieu;
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
            'categorie' => ['required', Rule::in(Signalement::CATEGORIE_OPTIONS)],
            'description' => ['required', 'string', 'max:5000'],
            'lieu' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['categorie' => __('catégorie'), 'lieu' => __('adresse ou lieu')];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Signalement::class);

        $validated = $this->validate();

        if ($this->photo) {
            $optimiseur = app(OptimiseurImage::class);
            $optimiseur->supprimer($this->record?->photo);
            // F60 : redimensionnée (1600 px max) et compressée en WebP à l'enregistrement.
            $validated['photo'] = $optimiseur->enregistrer($this->photo, 'signalements');
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

        Flux::toast(variant: 'success', text: $this->record ? __('Signalement mis à jour.') : __('Signalement envoyé à la mairie. Merci !'));

        $this->redirectRoute('signalements.show', $record, navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-2xl space-y-6">
    <x-tn.page-header
        label="{{ __('Signalements') }}"
        :title="$record ? __('Modifier le signalement') : __('Signaler un problème')"
        :subtitle="$record ? null : __('Lampadaire cassé, nid-de-poule, dépôt sauvage… Indiquez ce qui s’est passé et où : la mairie transmet au bon service.')"
        :breadcrumb="$record
            ? ['Mon espace' => route('dashboard'), 'Signalements' => route('signalements.index'), 'Signalement' => route('signalements.show', $record), 'Modifier' => null]
            : ['Mon espace' => route('dashboard'), 'Signalements' => route('signalements.index'), 'Nouveau' => null]"
    />

    <form wire:submit="save" class="space-y-6">
        <fieldset class="space-y-3">
            <legend class="tn-display mb-1 text-lg font-semibold text-ink">{{ __('Type de problème') }}</legend>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach (Signalement::CATEGORIE_OPTIONS as $option)
                    <label wire:key="categorie-{{ $option }}" class="flex min-h-14 cursor-pointer items-center gap-3 rounded-md border border-line bg-surface px-4 py-3 transition-colors hover:border-cyan/40 has-checked:border-cyan has-checked:bg-cyan/8">
                        <input type="radio" wire:model="categorie" value="{{ $option }}" class="size-4 accent-[var(--color-cyan)]">
                        <span class="font-medium text-ink">{{ __(Signalement::libelleCategorie($option)) }}</span>
                    </label>
                @endforeach
            </div>
            <flux:error name="categorie" />
        </fieldset>

        <flux:textarea wire:model="description" label="{{ __('Que s\'est-il passé ?') }}" placeholder="{{ __('Ex. Le lampadaire devant le n° 12 est cassé, la rue est dans le noir depuis trois jours.') }}" rows="5" required />

        <flux:input wire:model="lieu" label="{{ __('Adresse ou lieu') }}" icon="map-pin" placeholder="{{ __('Ex. Rue des Lumières, devant le n° 12') }}" required />

        <div class="space-y-3">
            <flux:input type="file" wire:model="photo" label="{{ __('Photo (facultative, 2 Mo max)') }}" accept="image/jpeg,image/png,image/webp" />
            <div wire:loading wire:target="photo"><flux:text>{{ __('Envoi en cours…') }}</flux:text></div>
            @if ($photo && ! $errors->has('photo'))
                <img src="{{ $photo->temporaryUrl() }}" alt="{{ __('Aperçu de la photo') }}" class="h-40 rounded-lg object-cover" />
            @elseif ($record?->photo)
                <x-tn.image :chemin="$record->photo" :alt="__('Photo du signalement')" sizes="320px" class="h-40 w-auto rounded-lg object-cover" />
            @endif
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-line pt-5">
            <flux:button :href="route('signalements.index')" wire:navigate variant="ghost">{{ __('Annuler') }}</flux:button>
            <flux:button type="submit" variant="primary" class="tn-cta">
                <span wire:loading.remove wire:target="save">{{ $record ? __('Enregistrer') : __('Envoyer le signalement') }}</span>
                <span wire:loading wire:target="save">{{ __('Envoi…') }}</span>
            </flux:button>
        </div>
    </form>
</section>
