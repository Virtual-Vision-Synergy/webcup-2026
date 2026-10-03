<?php

use App\Models\Projet;
use App\Models\Quartier;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Espace agent (F67) : création et mise à jour d'un projet de la ville (ProjetPolicy : agents et admins).
 */
new #[Layout('layouts::agent'), Title('Projet de la ville')] class extends Component {
    #[Locked]
    public ?Projet $record = null;

    public string $titre = '';
    public string $description = '';
    public string $etat = 'a_l_etude';

    /** '' = toute la ville. */
    public string $quartier_id = '';

    public string $date_debut = '';
    public string $date_fin = '';
    public string $budget = '';

    /** Étapes du projet, une par ligne. */
    public string $etapes = '';

    public string $etapes_terminees = '0';
    public string $latitude = '';
    public string $longitude = '';

    public function mount(?Projet $projet = null): void
    {
        if ($projet?->exists) {
            $this->authorize('update', $projet);
            $this->record = $projet;
            $this->titre = $projet->titre;
            $this->description = $projet->description;
            $this->etat = $projet->etat;
            $this->quartier_id = (string) ($projet->quartier_id ?? '');
            $this->date_debut = (string) $projet->date_debut?->format('Y-m-d');
            $this->date_fin = (string) $projet->date_fin?->format('Y-m-d');
            $this->budget = $projet->budget === null ? '' : (string) (int) $projet->budget;
            $this->etapes = (string) ($projet->etapes ?? '');
            $this->etapes_terminees = (string) $projet->etapes_terminees;
            $this->latitude = (string) ($projet->latitude ?? '');
            $this->longitude = (string) ($projet->longitude ?? '');
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
            'description' => ['required', 'string', 'max:5000'],
            'etat' => ['required', Rule::in(Projet::ETAT_OPTIONS)],
            'quartier_id' => ['nullable', 'integer', Rule::exists('quartiers', 'id')],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'budget' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'etapes' => ['nullable', 'string', 'max:3000'],
            'etapes_terminees' => ['required', 'integer', 'min:0', 'max:50'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'titre' => 'titre',
            'etat' => 'état',
            'quartier_id' => 'quartier',
            'date_debut' => 'date de début',
            'date_fin' => 'date de fin prévue',
            'etapes_terminees' => 'étapes réalisées',
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Projet::class);

        $validated = $this->validate();

        foreach (['quartier_id', 'date_debut', 'date_fin', 'budget', 'etapes', 'latitude', 'longitude'] as $field) {
            if (($validated[$field] ?? null) === '') {
                $validated[$field] = null;
            }
        }

        $record = $this->record ?? new Projet;
        $record->fill($validated);

        // On ne peut pas avoir réalisé plus d'étapes qu'il n'y en a.
        $record->etapes_terminees = min((int) $validated['etapes_terminees'], count($record->listeEtapes()));

        if (! $record->exists) {
            $record->user()->associate(auth()->user());
        }

        $record->save();

        Flux::toast(variant: 'success', text: 'Projet enregistré.');

        $this->redirectRoute('agent.projets.index', navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-2xl space-y-6">
    <x-tn.page-header
        label="Espace agent"
        :title="$record ? 'Mettre à jour le projet' : 'Nouveau projet'"
        :breadcrumb="$record
            ? ['Projets de la ville' => route('agent.projets.index'), $record->titre => route('projets.show', $record), 'Mettre à jour' => null]
            : ['Projets de la ville' => route('agent.projets.index'), 'Nouveau' => null]"
    />

    <form wire:submit="save" class="space-y-6 rounded-md border border-line bg-surface p-5 md:p-6">
        <flux:input wire:model="titre" label="Titre" placeholder="Ex. Réfection de l’avenue des Pionniers" required />

        <flux:textarea wire:model="description" label="Description en langage simple" description="Expliquez ce qui change pour les habitants, sans jargon technique." rows="5" required />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="etat" label="État" required>
                @foreach (Projet::ETAT_LABELS as $valeur => $label)
                    <flux:select.option value="{{ $valeur }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="quartier_id" label="Quartier">
                <flux:select.option value="">Toute la ville</flux:select.option>
                @foreach ($this->quartiers as $quartier)
                    <flux:select.option value="{{ $quartier->id }}">{{ $quartier->nom }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="date_debut" type="date" label="Date de début" />
            <flux:input wire:model="date_fin" type="date" label="Date de fin prévue" />
        </div>

        <flux:input wire:model="budget" type="number" min="0" label="Budget (Ariary, facultatif)" />

        <fieldset class="space-y-4">
            <flux:heading size="sm">Étapes et avancement</flux:heading>
            <flux:textarea wire:model="etapes" label="Étapes (une par ligne, dans l’ordre)" rows="5" placeholder="Concertation avec les habitants&#10;Travaux&#10;Mise en service" />
            <flux:input wire:model="etapes_terminees" type="number" min="0" max="50" label="Nombre d’étapes déjà réalisées" description="L’avancement affiché aux habitants est calculé à partir de ce nombre (100 % quand le projet est terminé)." />
        </fieldset>

        <fieldset class="space-y-4">
            <flux:heading size="sm">Lieu sur la carte (facultatif)</flux:heading>
            <flux:text>Cliquez sur la carte pour placer le projet : il apparaîtra sur sa fiche.</flux:text>

            <x-carte mode="choix" hauteur="16rem" label="Choisir l’emplacement du projet" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="latitude" label="Latitude" inputmode="decimal" />
                <flux:input wire:model="longitude" label="Longitude" inputmode="decimal" />
            </div>
        </fieldset>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            <flux:button :href="route('agent.projets.index')" wire:navigate variant="ghost">Annuler</flux:button>
            <span wire:loading wire:target="save" class="text-sm text-ink-2">Enregistrement…</span>
        </div>
    </form>
</section>
