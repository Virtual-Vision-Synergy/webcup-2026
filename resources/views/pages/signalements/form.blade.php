<?php

use App\Concerns\EmpecheEnvoiEnDouble;
use App\Concerns\ProtegeContreRobots;
use App\Concerns\ThrottlesPerUser;
use App\Models\Signalement;
use App\Services\OptimiseurImage;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Signalement')] class extends Component {
    use EmpecheEnvoiEnDouble, ProtegeContreRobots, ThrottlesPerUser, WithFileUploads;

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
            $this->initialiserAntiRobot('signalement');
            $this->initialiserJetonEnvoi();
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

        if ($this->record) {
            // F78 : 10 enregistrements par minute et par habitant au plus (message clair sous le formulaire).
            $this->throttlePerUser('signalement', maxAttempts: 10, decaySeconds: 60);
        } else {
            // F81 : champ piège, délai minimal, 10 envois par minute par compte et 30 par IP (journalisés si bloqués).
            $this->verifierAntiRobot(
                'signalement',
                parCompte: (int) config('security.formulaires.limites.signalement.compte'),
                parIp: (int) config('security.formulaires.limites.signalement.ip'),
            );
        }

        if ($this->record) {
            $this->enregistrerPhoto($validated);
            $this->record->update($validated);
            $record = $this->record;
        } else {
            // F82 : même signalement renvoyé (double clic, retour arrière) → rien n'est créé, message avec lien.
            $record = $this->envoyerUneSeuleFois(
                'signalement',
                ['categorie' => $validated['categorie'], 'description' => $validated['description'], 'lieu' => $validated['lieu']],
                function () use ($validated): Signalement {
                    $this->enregistrerPhoto($validated);
                    $record = new Signalement($validated);
                    $record->user()->associate(auth()->user());
                    $record->save();

                    return $record;
                },
                fn (Signalement $signalement): string => route('signalements.show', $signalement),
            );

            if ($record === null) {
                return;
            }
        }

        Flux::toast(variant: 'success', text: $this->record ? __('Signalement mis à jour.') : __('Signalement envoyé à la mairie. Merci !'));

        $this->redirectRoute('signalements.show', $record, navigate: true);
    }

    /**
     * F60 : photo redimensionnée (1600 px max) et compressée en WebP à l'enregistrement.
     *
     * @param  array<string, mixed>  $validated
     */
    private function enregistrerPhoto(array &$validated): void
    {
        if ($this->photo) {
            $optimiseur = app(OptimiseurImage::class);
            $optimiseur->supprimer($this->record?->photo);
            $validated['photo'] = $optimiseur->enregistrer($this->photo, 'signalements');
        } else {
            unset($validated['photo']);
        }
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

    <form wire:submit="save" class="relative space-y-6">
        <x-tn.mention-obligatoire />

        @unless ($record)
            <x-anti-robot-livewire />
        @endunless

        <fieldset class="space-y-3" data-requis>
            <legend class="tn-display mb-1 text-lg font-semibold text-ink">{{ __('Type de problème') }}</legend>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach (Signalement::CATEGORIE_OPTIONS as $option)
                    <label wire:key="categorie-{{ $option }}" class="flex min-h-14 cursor-pointer items-center gap-3 rounded-md border border-line bg-surface px-4 py-3 transition-colors hover:border-cyan/40 has-checked:border-cyan has-checked:bg-cyan/8">
                        <input type="radio" wire:model="categorie" name="categorie" value="{{ $option }}" required class="size-4 accent-[var(--color-cyan)]">
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

        <flux:error name="throttle" />

        <x-envoi-deja-fait :le="$envoiDejaFaitLe" :url="$envoiDejaFaitUrl" />

        <div class="flex items-center justify-between gap-3 border-t border-line pt-5">
            <flux:button :href="route('signalements.index')" wire:navigate variant="ghost">{{ __('Annuler') }}</flux:button>
            <x-submit-button variant="primary" class="tn-cta">{{ $record ? __('Enregistrer') : __('Envoyer le signalement') }}</x-submit-button>
        </div>
    </form>
</section>
