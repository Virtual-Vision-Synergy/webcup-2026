<?php

use App\Models\Projet;
use App\Models\Quartier;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Création et mise à jour d'un projet de la ville (F67) : agents et admins uniquement (ProjetPolicy).
 */
new #[Title('Projet de la ville')] class extends Component {
    #[Locked]
    public ?Projet $record = null;

    public string $titre = '';
    public string $categorie = 'voirie';
    public string $quartier_id = '';
    public string $resume = '';
    public string $description = '';
    public string $etat = 'etude';
    public string $etapes = '';
    public string $etapes_terminees = '0';
    public string $avancement = '0';
    public string $date_debut = '';
    public string $date_fin = '';
    public string $budget = '';
    public string $lieu = '';
    public string $latitude = '';
    public string $longitude = '';

    public bool $consultation_ouverte = false;

    public function mount(?Projet $projet = null): void
    {
        if ($projet?->exists) {
            $this->authorize('update', $projet);
            $this->record = $projet;
            $this->titre = $projet->titre;
            $this->categorie = $projet->categorie;
            $this->quartier_id = (string) ($projet->quartier_id ?? '');
            $this->resume = $projet->resume;
            $this->description = $projet->description;
            $this->etat = $projet->etat;
            $this->etapes = (string) ($projet->etapes ?? '');
            $this->etapes_terminees = (string) $projet->etapes_terminees;
            $this->avancement = (string) $projet->avancement;
            $this->date_debut = (string) $projet->date_debut?->format('Y-m-d');
            $this->date_fin = (string) $projet->date_fin?->format('Y-m-d');
            $this->budget = (string) ($projet->budget ?? '');
            $this->lieu = (string) ($projet->lieu ?? '');
            $this->latitude = (string) ($projet->latitude ?? '');
            $this->longitude = (string) ($projet->longitude ?? '');
            $this->consultation_ouverte = $projet->consultation_ouverte;
        } else {
            $this->authorize('create', Projet::class);
        }
    }

    /**
     * @return Collection<int, Quartier>
     */
    #[Computed]
    public function quartiers(): Collection
    {
        return Quartier::query()->orderBy('nom')->get(['id', 'nom']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'categorie' => ['required', Rule::in(Projet::CATEGORIE_OPTIONS)],
            'quartier_id' => ['nullable', 'integer', Rule::exists('quartiers', 'id')],
            'resume' => ['required', 'string', 'max:300'],
            'description' => ['required', 'string', 'max:10000'],
            'etat' => ['required', Rule::in(Projet::ETAT_OPTIONS)],
            'etapes' => ['nullable', 'string', 'max:5000'],
            'etapes_terminees' => ['required', 'integer', 'min:0', 'max:50'],
            'avancement' => ['required', 'integer', 'min:0', 'max:100'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'budget' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'lieu' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'consultation_ouverte' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'categorie' => __('type de projet'),
            'quartier_id' => __('quartier'),
            'resume' => __('résumé'),
            'etat' => __('état'),
            'etapes' => __('étapes'),
            'etapes_terminees' => __('étapes terminées'),
            'date_debut' => __('date de début'),
            'date_fin' => __('date de fin'),
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Projet::class);

        $validated = $this->validate();

        $nombreEtapes = count(array_filter(array_map('trim', preg_split('/\R/', (string) $validated['etapes']) ?: [])));

        $record = $this->record ?? new Projet;
        $record->fill([
            'titre' => $validated['titre'],
            'categorie' => $validated['categorie'],
            'quartier_id' => $validated['quartier_id'] ?: null,
            'resume' => $validated['resume'],
            'description' => $validated['description'],
            'etat' => $validated['etat'],
            'etapes' => $validated['etapes'] ?: null,
            'etapes_terminees' => min((int) $validated['etapes_terminees'], $nombreEtapes),
            'avancement' => $validated['etat'] === 'termine' ? 100 : (int) $validated['avancement'],
            'date_debut' => $validated['date_debut'] ?: null,
            'date_fin' => $validated['date_fin'] ?: null,
            'budget' => $validated['budget'] === null || $validated['budget'] === '' ? null : (int) $validated['budget'],
            'lieu' => $validated['lieu'] ?: null,
            'latitude' => $validated['latitude'] ?: null,
            'longitude' => $validated['longitude'] ?: null,
        ]);

        // F66 : hors #[Fillable], assigné ici (formulaire réservé aux agents et admins).
        $record->consultation_ouverte = (bool) $validated['consultation_ouverte'];

        if (! $record->exists) {
            $record->user()->associate(auth()->user());
        }

        $record->save();

        Flux::toast(variant: 'success', text: __('Projet enregistré.'));

        $this->redirectRoute('projets.show', $record, navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-2xl space-y-6">
    <x-tn.page-header
        label="{{ __('Vie de la ville') }}"
        :title="$record ? __('Mettre à jour le projet') : __('Nouveau projet')"
        :breadcrumb="$record
            ? ['Mon espace' => route('dashboard'), 'Projets de la ville' => route('projets.index'), $record->titre => route('projets.show', $record), 'Mettre à jour' => null]
            : ['Mon espace' => route('dashboard'), 'Projets de la ville' => route('projets.index'), 'Nouveau projet' => null]"
    />

    <form wire:submit="save" class="space-y-6 rounded-md border border-line bg-surface p-5 md:p-6">
        <x-tn.mention-obligatoire />

        <flux:input wire:model="titre" label="{{ __('Titre') }}" placeholder="{{ __('Réfection de la rue des Palmiers') }}" required />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="categorie" label="{{ __('Type de projet') }}" required>
                @foreach (Projet::CATEGORIE_LABELS as $value => $label)
                    <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="quartier_id" label="{{ __('Quartier') }}">
                <flux:select.option value="">{{ __('Toute la ville') }}</flux:select.option>
                @foreach ($this->quartiers as $q)
                    <flux:select.option :value="$q->id">{{ $q->nom }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:input wire:model="resume" label="{{ __('Résumé en une phrase') }}" description="{{ __('Ce que les habitants y gagnent, sans jargon.') }}" maxlength="300" required />

        <flux:textarea wire:model="description" label="{{ __('Description en langage simple') }}" description="{{ __('Pourquoi ce projet, ce qui va changer, ce que cela implique pour les habitants pendant les travaux.') }}" rows="6" required />

        <fieldset class="space-y-4">
            <flux:heading size="sm">{{ __('Avancement') }}</flux:heading>

            <flux:select wire:model.live="etat" label="{{ __('État') }}" required>
                @foreach (Projet::ETAT_LABELS as $value => $label)
                    <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="etapes" label="{{ __('Étapes (une par ligne, dans l\'ordre)') }}" rows="5" placeholder="{{ __('Concertation, choix de l\'entreprise, travaux, mise en service…') }}" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="etapes_terminees" type="number" min="0" max="50" label="{{ __('Étapes terminées') }}" required />
                @if ($etat !== 'termine')
                    <flux:input wire:model="avancement" type="number" min="0" max="100" label="{{ __('Avancement (%)') }}" required />
                @endif
            </div>
        </fieldset>

        <fieldset class="space-y-4">
            <flux:heading size="sm">{{ __('Dates et budget') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="date_debut" type="date" label="{{ __('Date de début') }}" />
                <flux:input wire:model="date_fin" type="date" label="{{ __('Date de fin (prévue)') }}" />
            </div>
            <flux:input wire:model="budget" type="number" min="0" label="{{ __('Budget en ariary (facultatif)') }}" />
        </fieldset>

        <fieldset class="space-y-4">
            <flux:heading size="sm">{{ __('Lieu') }}</flux:heading>
            <flux:input wire:model="lieu" label="{{ __('Adresse ou secteur') }}" placeholder="{{ __('Avenue de l\'Indépendance, de la gare à l\'hôtel de ville') }}" />
            <flux:text>{{ __('Placez le projet sur la carte (facultatif) : il apparaîtra sur la fiche et sur la carte des projets.') }}</flux:text>
            <x-carte mode="choix" hauteur="16rem" :label="__('Choisir l\'emplacement du projet')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="latitude" label="{{ __('Latitude') }}" inputmode="decimal" />
                <flux:input wire:model="longitude" label="{{ __('Longitude') }}" inputmode="decimal" />
            </div>
        </fieldset>

        <fieldset class="space-y-4">
            <flux:heading size="sm">{{ __('Consultation des habitants') }}</flux:heading>
            <flux:checkbox wire:model="consultation_ouverte" label="{{ __('Ouvrir ce projet à l\'avis des habitants') }}" description="{{ __('Les habitants connectés pourront répondre pour, contre ou sans avis, avec un commentaire. Ce n\'est pas un vote officiel.') }}" />
        </fieldset>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="save">{{ __('Enregistrer') }}</span>
                <span wire:loading wire:target="save">{{ __('Enregistrement…') }}</span>
            </flux:button>
            <flux:button :href="$record ? route('projets.show', $record) : route('projets.index')" wire:navigate variant="ghost">{{ __('Annuler') }}</flux:button>
        </div>
    </form>
</section>
