<?php

use App\Models\Role;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rôle')] class extends Component {
    #[Locked]
    public ?Role $record = null;

    public string $code = '';
    public string $label = '';

    public function mount(?Role $role = null): void
    {
        if ($role?->exists) {
            $this->authorize('update', $role);
            $this->record = $role;
            $this->code = (string) ($role->code ?? '');
            $this->label = (string) ($role->label ?? '');
        } else {
            $this->authorize('create', Role::class);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique(Role::class, 'code')->ignore($this->record)],
            'label' => ['required', 'string', 'max:255'],
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Role::class);

        $validated = $this->validate();

        // Le code d'un rôle de base (citoyen, agent, admin) est utilisé par le code : il ne change jamais.
        if ($this->record && in_array($this->record->code, Role::CODES, true)) {
            unset($validated['code']);
        }

        if ($this->record) {
            $this->record->update($validated);
            $record = $this->record;
        } else {
            $record = Role::create($validated);
        }

        Flux::toast(variant: 'success', text: 'Rôle enregistré(e).');

        $this->redirectRoute('roles.show', $record, navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:link :href="route('roles.index')" wire:navigate class="text-sm">&larr; Rôles</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">
            {{ $record ? 'Modifier' : 'Ajouter' }} : Rôle
        </flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="code" label="Code" required :disabled="$record && in_array($record->code, \App\Models\Role::CODES, true)" />

        <flux:input wire:model="label" label="Libellé" required />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            <flux:button :href="route('roles.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
</section>
