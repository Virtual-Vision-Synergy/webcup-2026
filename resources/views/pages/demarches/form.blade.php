<?php

use App\Models\Demarche;
use App\Models\Service;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Démarche')] class extends Component {
    #[Locked]
    public ?Demarche $record = null;

    public string $titre = '';
    public string $description = '';
    public string $service_id = '';

    public function mount(?Demarche $demarche = null): void
    {
        if ($demarche?->exists) {
            $this->authorize('update', $demarche);
            $this->record = $demarche;
            $this->titre = (string) ($demarche->titre ?? '');
            $this->description = (string) ($demarche->description ?? '');
            $this->service_id = (string) ($demarche->service_id ?? '');
        } else {
            $this->authorize('create', Demarche::class);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'service_id' => ['nullable', Rule::exists(Service::class, 'id')],
        ];
    }

    /**
     * Valeurs proposées dans la liste déroulante « Service ».
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function serviceOptions(): Collection
    {
        return Service::query()->orderBy('nom')->get(['id', 'nom']);
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Demarche::class);

        $validated = $this->validate();

        foreach (['service_id'] as $field) {
            if (($validated[$field] ?? null) === '') {
                $validated[$field] = null;
            }
        }

        if ($this->record) {
            $this->record->update($validated);
            $record = $this->record;
        } else {
            $record = new Demarche($validated);
            $record->user()->associate(auth()->user());
            $record->save();
        }

        Flux::toast(variant: 'success', text: 'Démarche enregistrée.');

        $this->redirectRoute('demarches.show', $record, navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:link :href="route('demarches.index')" wire:navigate class="text-sm">&larr; Mes démarches</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">
            {{ $record ? 'Modifier la démarche' : 'Nouvelle démarche' }}
        </flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="titre" label="Objet de la démarche" placeholder="Ex. Demande d'acte de naissance" required />

        <flux:textarea wire:model="description" label="Détails" placeholder="Précisez votre demande (personnes concernées, dates, pièces disponibles…)" rows="5" required />

        <flux:select wire:model="service_id" label="Service concerné">
            <flux:select.option value="">— Je ne sais pas —</flux:select.option>
            @foreach ($this->serviceOptions as $option)
                <flux:select.option :value="$option->id">{{ $option->nom }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </flux:button>
            <flux:button :href="route('demarches.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
</section>
