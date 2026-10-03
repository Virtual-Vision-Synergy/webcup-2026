<?php

use App\Models\Service;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Service municipal')] class extends Component {
    #[Locked]
    public ?Service $record = null;

    public string $nom = '';
    public string $description = '';
    public string $horaires = '';
    public string $telephone = '';
    public string $email = '';
    public string $adresse = '';

    public function mount(?Service $service = null): void
    {
        if ($service?->exists) {
            $this->authorize('update', $service);
            $this->record = $service;
            $this->nom = (string) ($service->nom ?? '');
            $this->description = (string) ($service->description ?? '');
            $this->horaires = (string) ($service->horaires ?? '');
            $this->telephone = (string) ($service->telephone ?? '');
            $this->email = (string) ($service->email ?? '');
            $this->adresse = (string) ($service->adresse ?? '');
        } else {
            $this->authorize('create', Service::class);
        }
    }

    /**
     * Au moins les horaires OU un moyen de contact (téléphone, e-mail, adresse) doivent être renseignés.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'horaires' => ['nullable', 'required_without_all:telephone,email,adresse', 'string', 'max:2000'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'horaires.required_without_all' => 'Renseignez au moins les horaires ou un moyen de contact (téléphone, e-mail ou adresse).',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nom' => 'nom du service',
            'horaires' => "horaires d'ouverture",
            'telephone' => 'téléphone',
            'email' => 'adresse e-mail',
            'adresse' => 'adresse',
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Service::class);

        $validated = $this->validate();

        foreach (['horaires', 'telephone', 'email', 'adresse'] as $field) {
            if (($validated[$field] ?? null) === '') {
                $validated[$field] = null;
            }
        }

        if ($this->record) {
            $this->record->update($validated);
            $record = $this->record;
        } else {
            $record = new Service($validated);
            $record->user()->associate(auth()->user());
            $record->save();
        }

        Flux::toast(variant: 'success', text: 'Service municipal enregistré.');

        $this->redirectRoute('services.show', $record, navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:link :href="route('services.index')" wire:navigate class="text-sm">&larr; Services municipaux</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">
            {{ $record ? 'Modifier le service' : 'Ajouter un service' }}
        </flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="nom" label="Nom du service" placeholder="Ex. : État civil" required />

        <flux:textarea wire:model="description" label="Description" rows="5" placeholder="Missions du service, démarches possibles…" required />

        <flux:textarea wire:model="horaires" label="Horaires d'ouverture" rows="3" placeholder="Lundi au vendredi : 8 h – 16 h" />

        <flux:text class="text-sm">Renseignez au moins les horaires ou un moyen de contact.</flux:text>

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="telephone" label="Téléphone" type="tel" placeholder="+261 20 22 000 00" />

            <flux:input wire:model="email" label="Adresse e-mail" type="email" placeholder="service@mairie-novaterra.mg" />
        </div>

        <flux:input wire:model="adresse" label="Adresse" placeholder="Hôtel de ville, place de l'Indépendance" />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </flux:button>
            <flux:button :href="route('services.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
</section>
