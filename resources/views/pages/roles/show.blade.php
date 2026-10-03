<?php

use App\Models\Role;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rôle')] class extends Component {
    #[Locked]
    public Role $record;

    public function mount(Role $role): void
    {
        $this->authorize('view', $role);
        $this->record = $role->loadCount('users');
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);

        $this->record->delete();

        Flux::toast(variant: 'success', text: __('Rôle supprimé(e).'));

        $this->redirectRoute('roles.index', navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6">
    <x-tn.page-header
        label="{{ __('Administration') }}"
        :title="$record->code"
        :subtitle="__($record->label).' · '.__(':n compte(s)', ['n' => $record->users_count])"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Rôles' => route('roles.index'), $record->code => null]"
    >
        <x-slot:actions>
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('roles.edit', $record)" wire:navigate>{{ __('Modifier') }}</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Supprimer définitivement cet élément ?') }}">{{ __('Supprimer') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <x-audit-history :subject="$record" variant="resume" />



    <flux:card>
        <dl class="divide-y divide-zinc-200 dark:divide-zinc-700">
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Code') }}</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0">{{ $record->code ?? '—' }}</dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Libellé') }}</dt>
                <dd class="mt-1 text-sm sm:col-span-2 sm:mt-0">{{ $record->label ?? '—' }}</dd>
            </div>
        </dl>
    </flux:card>

    <x-audit-history :subject="$record" />
</section>
