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

        Flux::toast(variant: 'success', text: __('Rôle enregistré(e).'));

        $this->redirectRoute('roles.show', $record, navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-2xl space-y-6">
    <x-tn.page-header
        label="{{ __('Administration') }}"
        :title="$record ? __('Modifier le rôle') : __('Ajouter un rôle')"
        :breadcrumb="$record
            ? ['Mon espace' => route('dashboard'), 'Rôles' => route('roles.index'), $record->code => route('roles.show', $record), 'Modifier' => null]
            : ['Mon espace' => route('dashboard'), 'Rôles' => route('roles.index'), 'Nouveau' => null]"
    />

    <form wire:submit="save" class="space-y-6 rounded-md border border-line bg-surface p-5 md:p-6">
        <flux:input wire:model="code" label="{{ __('Code') }}" required :disabled="$record && in_array($record->code, \App\Models\Role::CODES, true)" />

        <flux:input wire:model="label" label="{{ __('Libellé') }}" required />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
            <flux:button :href="route('roles.index')" wire:navigate variant="ghost">{{ __('Annuler') }}</flux:button>
        </div>
    </form>
</section>
