<?php

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
    public bool $mis_en_avant = false;

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
        } else {
            $this->authorize('create', Service::class);
        }
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
            'mis_en_avant' => ['boolean'],
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Service::class);

        $validated = $this->validate();
        $miseEnAvant = (bool) ($validated['mis_en_avant'] ?? false);
        unset($validated['mis_en_avant']);

        foreach (['lieu_rendez_vous', 'duree_rendez_vous', 'pieces_a_fournir'] as $field) {
            if (($validated[$field] ?? null) === '') {
                $validated[$field] = null;
            }
        }

        if ($this->record) {
            $this->record->update($validated);
            $record = $this->record;
        } else {
            $record = new Service($validated);
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

        Cache::forget('landing.etat');

        Flux::toast(variant: 'success', text: 'Service enregistré(e).');

        $this->redirectRoute('services.show', $record, navigate: true);
    }
} ?>

<section class="mx-auto w-full max-w-2xl space-y-6">
    <x-tn.page-header
        label="Annuaire"
        :title="$record ? 'Modifier le service' : 'Ajouter un service'"
        :breadcrumb="$record
            ? ['Mon espace' => route('dashboard'), 'Services' => route('services.index'), $record->nom => route('services.show', $record), 'Modifier' => null]
            : ['Mon espace' => route('dashboard'), 'Services' => route('services.index'), 'Nouveau' => null]"
    />

    <form wire:submit="save" class="space-y-6 rounded-md border border-line bg-surface p-5 md:p-6">
        <flux:input wire:model="nom" label="Nom" required />

        <flux:textarea wire:model="description" label="Description" rows="5" required />

        <flux:select wire:model="categorie" label="Catégorie" placeholder="Choisir une catégorie…" required>
            @foreach (Service::CATEGORIE_LABELS as $valeur => $label)
                <flux:select.option value="{{ $valeur }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <fieldset class="space-y-4">
            <flux:heading size="sm">Prise de rendez-vous</flux:heading>
            <flux:text>Laissez la durée vide si le service ne prend pas de rendez-vous en ligne.</flux:text>

            <flux:input wire:model="duree_rendez_vous" type="number" min="5" max="240" label="Durée d'un rendez-vous (minutes)" />

            <flux:input wire:model="lieu_rendez_vous" label="Lieu du rendez-vous" placeholder="Bâtiment, étage, guichet" />

            <flux:textarea wire:model="pieces_a_fournir" label="Pièces à apporter (une par ligne)" rows="4" />
        </fieldset>

        @can('feature', $record ?? Service::class)
            <flux:checkbox wire:model="mis_en_avant" label="Mettre en avant" description="Le service apparaît en tête du catalogue et sur la page d'accueil." />
        @endcan

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            <flux:button :href="route('services.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
</section>
