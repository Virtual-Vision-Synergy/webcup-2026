<?php

use App\Http\Middleware\DefinirLangue;
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
    public string $categorie = '';
    public string $lieu_rendez_vous = '';
    public string $duree_rendez_vous = '';
    public string $pieces_a_fournir = '';
    public string $latitude = '';
    public string $longitude = '';
    public bool $mis_en_avant = false;

    /**
     * F27 : traductions facultatives, par langue proposée (hors français) puis par champ traduisible.
     *
     * @var array<string, array<string, string>>
     */
    public array $traductions = [];

    public function mount(?Service $service = null): void
    {
        if ($service?->exists) {
            $this->authorize('update', $service);
            $this->record = $service;
            $this->nom = (string) ($service->nom ?? '');
            $this->description = (string) ($service->description ?? '');
            $this->categorie = (string) ($service->categorie ?? '');
            $this->mis_en_avant = (bool) $service->mis_en_avant;
            $this->lieu_rendez_vous = (string) ($service->lieu_rendez_vous ?? '');
            $this->duree_rendez_vous = (string) ($service->duree_rendez_vous ?? '');
            $this->pieces_a_fournir = (string) ($service->pieces_a_fournir ?? '');
            $this->latitude = (string) ($service->latitude ?? '');
            $this->longitude = (string) ($service->longitude ?? '');

            if (auth()->user()->can('translate', $service)) {
                $this->remplirTraductions($service);
            }
        } else {
            $this->authorize('create', Service::class);
        }
    }

    /**
     * F27 : une entrée par langue de traduction, chaque champ pré-rempli avec la traduction existante (sinon vide).
     */
    private function remplirTraductions(Service $service): void
    {
        $service->loadMissing('translations');

        foreach (array_keys(DefinirLangue::languesDeTraduction()) as $locale) {
            $traduction = $service->traduction($locale);

            foreach (Service::TRADUCTIBLES as $champ) {
                $this->traductions[$locale][$champ] = (string) ($traduction?->getAttribute($champ) ?? '');
            }
        }
    }

    /**
     * F27 : règles des traductions (toutes facultatives), limitées aux langues proposées par la configuration.
     *
     * @return array<string, mixed>
     */
    private function reglesTraductions(): array
    {
        $regles = ['traductions' => ['array']];
        $longueurs = ['nom' => 255, 'description' => 5000, 'horaires' => 1000, 'adresse' => 255, 'lieu_rendez_vous' => 255, 'pieces_a_fournir' => 2000];

        foreach (array_keys(DefinirLangue::languesDeTraduction()) as $locale) {
            foreach ($longueurs as $champ => $max) {
                $regles["traductions.{$locale}.{$champ}"] = ['nullable', 'string', 'max:'.$max];
            }
        }

        return $regles;
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
            'lieu_rendez_vous' => ['nullable', 'string', 'max:255'],
            'duree_rendez_vous' => ['nullable', 'integer', 'min:5', 'max:240'],
            'pieces_a_fournir' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'mis_en_avant' => ['boolean'],
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Service::class);

        $peutTraduire = $this->record !== null && auth()->user()->can('translate', $this->record);

        $validated = $this->validate($peutTraduire ? [...$this->rules(), ...$this->reglesTraductions()] : $this->rules());
        $miseEnAvant = (bool) ($validated['mis_en_avant'] ?? false);
        $traductions = (array) ($validated['traductions'] ?? []);
        unset($validated['mis_en_avant'], $validated['traductions']);

        foreach (['lieu_rendez_vous', 'duree_rendez_vous', 'pieces_a_fournir', 'latitude', 'longitude'] as $field) {
            if (($validated[$field] ?? null) === '') {
                $validated[$field] = null;
            }
        }

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

        // F27 : traductions enregistrées uniquement pour les langues proposées, après une autorisation explicite.
        if ($peutTraduire) {
            $this->authorize('translate', $record);

            foreach (array_keys(DefinirLangue::languesDeTraduction()) as $locale) {
                $record->enregistrerTraduction($locale, (array) ($traductions[$locale] ?? []));
            }
        }

        Cache::forget('landing.etat');

        Flux::toast(variant: 'success', text: __('Service enregistré(e).'));

        $this->redirectRoute('services.show', $record, navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-2xl space-y-6">
    <x-tn.page-header
        label="{{ __('Annuaire') }}"
        :title="$record ? __('Modifier le service') : __('Ajouter un service')"
        :breadcrumb="$record
            ? [__('Mon espace') => route('dashboard'), __('Services') => route('services.index'), $record->t('nom') => route('services.show', $record), __('Modifier') => null]
            : [__('Mon espace') => route('dashboard'), __('Services') => route('services.index'), __('Nouveau') => null]"
    />

    <form wire:submit="save" class="space-y-6 rounded-md border border-line bg-surface p-5 md:p-6">
        <flux:input wire:model="nom" label="{{ __('Nom') }}" required />

        <flux:textarea wire:model="description" label="{{ __('Description') }}" rows="5" required />

        <flux:select wire:model="categorie" label="{{ __('Catégorie') }}" placeholder="{{ __('Choisir une catégorie…') }}" required>
            @foreach (Service::CATEGORIE_LABELS as $valeur => $label)
                <flux:select.option value="{{ $valeur }}">{{ __($label) }}</flux:select.option>
            @endforeach
        </flux:select>

        <fieldset class="space-y-4">
            <flux:heading size="sm">{{ __('Lieu d\'accueil sur la carte') }}</flux:heading>
            <flux:text>{{ __('Placez le lieu où les habitants sont reçus : il apparaîtra sur la carte des services.') }}</flux:text>

            <x-carte mode="choix" hauteur="16rem" :label="__('Choisir l\'emplacement du service')" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="latitude" label="{{ __('Latitude') }}" inputmode="decimal" />
                <flux:input wire:model="longitude" label="{{ __('Longitude') }}" inputmode="decimal" />
            </div>
        </fieldset>

        <fieldset class="space-y-4">
            <flux:heading size="sm">{{ __('Prise de rendez-vous') }}</flux:heading>
            <flux:text>{{ __('Laissez la durée vide si le service ne prend pas de rendez-vous en ligne.') }}</flux:text>

            <flux:input wire:model="duree_rendez_vous" type="number" min="5" max="240" label="{{ __('Durée d\'un rendez-vous (minutes)') }}" />

            <flux:input wire:model="lieu_rendez_vous" label="{{ __('Lieu du rendez-vous') }}" placeholder="{{ __('Bâtiment, étage, guichet') }}" />

            <flux:textarea wire:model="pieces_a_fournir" label="{{ __('Pièces à apporter (une par ligne)') }}" rows="4" />
        </fieldset>

        {{-- F27 : versions traduites (facultatives) ; le français ci-dessus reste la référence et sert de repli. --}}
        @if ($record && auth()->user()->can('translate', $record))
            <fieldset class="space-y-4" x-data="{ langue: @js(array_key_first(DefinirLangue::languesDeTraduction())) }">
                <flux:heading size="sm">{{ __('Traductions') }}</flux:heading>
                <flux:text>{{ __('Facultatif : un champ laissé vide s\'affiche en français, avec une mention pour l\'habitant.') }}</flux:text>

                <div class="flex flex-wrap gap-1" role="tablist" aria-label="{{ __('Langue de la traduction') }}">
                    <span class="inline-flex h-9 items-center rounded-sm border border-line px-3 text-sm text-ink-2" lang="fr">{{ __('Français (référence)') }}</span>
                    @foreach (DefinirLangue::languesDeTraduction() as $code => $libelle)
                        <button
                            type="button"
                            role="tab"
                            lang="{{ $code }}"
                            x-on:click="langue = @js($code)"
                            x-bind:aria-selected="langue === @js($code) ? 'true' : 'false'"
                            x-bind:class="langue === @js($code) ? 'bg-cyan/12 text-cyan border-cyan/40' : 'text-ink-2 border-line hover:text-ink'"
                            class="inline-flex h-9 items-center rounded-sm border px-3 text-sm font-medium"
                        >{{ $libelle }}</button>
                    @endforeach
                </div>

                @foreach (DefinirLangue::languesDeTraduction() as $code => $libelle)
                    <div wire:key="traduction-{{ $code }}" x-show="langue === @js($code)" x-cloak role="tabpanel" class="space-y-4" lang="{{ $code }}">
                        <flux:input wire:model="traductions.{{ $code }}.nom" label="{{ __('Nom') }} ({{ $libelle }})" :placeholder="$record->nom" />
                        <flux:textarea wire:model="traductions.{{ $code }}.description" label="{{ __('Description') }} ({{ $libelle }})" rows="4" />
                        <flux:textarea wire:model="traductions.{{ $code }}.horaires" label="{{ __('Horaires') }} ({{ $libelle }})" rows="3" :placeholder="$record->horaires" />
                        <flux:input wire:model="traductions.{{ $code }}.adresse" label="{{ __('Adresse') }} ({{ $libelle }})" :placeholder="$record->adresse" />
                        <flux:input wire:model="traductions.{{ $code }}.lieu_rendez_vous" label="{{ __('Lieu du rendez-vous') }} ({{ $libelle }})" />
                        <flux:textarea wire:model="traductions.{{ $code }}.pieces_a_fournir" label="{{ __('Pièces à apporter (une par ligne)') }} ({{ $libelle }})" rows="4" />
                    </div>
                @endforeach
            </fieldset>
        @endif

        @can('feature', $record ?? Service::class)
            <flux:checkbox wire:model="mis_en_avant" label="{{ __('Mettre en avant') }}" description="{{ __('Le service apparaît en tête du catalogue et sur la page d\'accueil.') }}" />
        @endcan

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
            <flux:button :href="route('services.index')" wire:navigate variant="ghost">{{ __('Annuler') }}</flux:button>
        </div>
    </form>
</section>
