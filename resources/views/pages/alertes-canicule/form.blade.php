<?php

use App\Models\AlerteCanicule;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Alerte canicule')] class extends Component {
    #[Locked]
    public ?AlerteCanicule $record = null;

    public string $secteur = '';
    public string $niveau = 'vigilance';
    public string $temperature_max = '';
    public string $debut = '';
    public string $fin = '';
    public string $message = '';

    public function mount(?AlerteCanicule $alerte_canicule = null): void
    {
        if ($alerte_canicule?->exists) {
            $this->authorize('update', $alerte_canicule);
            $this->record = $alerte_canicule;
            $this->secteur = $alerte_canicule->secteur;
            $this->niveau = $alerte_canicule->niveau;
            $this->temperature_max = (string) $alerte_canicule->temperature_max;
            $this->debut = $alerte_canicule->debut->format('Y-m-d');
            $this->fin = $alerte_canicule->fin?->format('Y-m-d') ?? '';
            $this->message = (string) ($alerte_canicule->message ?? '');
        } else {
            $this->authorize('create', AlerteCanicule::class);
            $this->debut = today()->format('Y-m-d');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'secteur' => ['required', 'string', 'max:255'],
            'niveau' => ['required', Rule::in(AlerteCanicule::NIVEAU_OPTIONS)],
            'temperature_max' => ['required', 'integer', 'between:25,60'],
            'debut' => ['required', 'date'],
            'fin' => ['nullable', 'date', 'after_or_equal:debut'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', AlerteCanicule::class);

        $validated = $this->validate();
        $validated['fin'] = $validated['fin'] ?: null;
        $validated['message'] = $validated['message'] ?: null;

        if ($this->record) {
            $this->record->update($validated);
        } else {
            $record = new AlerteCanicule($validated);
            $record->user()->associate(auth()->user());
            $record->save();
        }

        Flux::toast(variant: 'success', text: 'Alerte enregistrée.');

        $this->redirectRoute('alertes-canicule.index', navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-2xl space-y-6">
    <x-tn.page-header
        label="Agence sanitaire"
        :title="$record ? 'Modifier l’alerte' : 'Publier une alerte canicule'"
        :breadcrumb="['Alertes canicule' => route('alertes-canicule.index'), ($record ? 'Modifier' : 'Nouvelle') => null]"
    />

    <form wire:submit="save" class="space-y-6 rounded-md border border-line bg-surface p-5 md:p-6">
        <flux:input wire:model="secteur" label="Secteur" required />

        <flux:select wire:model="niveau" label="Niveau d'alerte">
            @foreach (App\Models\AlerteCanicule::NIVEAU_LABELS as $valeur => $libelle)
                <flux:select.option value="{{ $valeur }}">{{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="temperature_max" label="Température maximale prévue (°C)" type="number" min="25" max="60" required />

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="debut" label="Début" type="date" required />
            <flux:input wire:model="fin" label="Fin (facultatif)" type="date" />
        </div>

        <flux:textarea wire:model="message" label="Message (facultatif)" rows="3" />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            <flux:button :href="route('alertes-canicule.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
</section>
