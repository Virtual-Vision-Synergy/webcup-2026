<?php

use App\Models\Message;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Message')] class extends Component {
    #[Locked]
    public Message $record;

    public function mount(Message $message): void
    {
        $this->authorize('view', $message);
        $this->record = $message;
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);

        $this->record->delete();

        Flux::toast(variant: 'success', text: __('Message supprimé(e).'));

        $this->redirectRoute('messages.index', navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6">
    <x-tn.page-header
        label="{{ __('Message') }}"
        :title="$record->sujet ?? __('Sans objet')"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Messages' => route('messages.index'), ($record->sujet ?: 'Message') => null]"
    >
        <x-slot:actions>
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('messages.edit', $record)" wire:navigate>{{ __('Modifier') }}</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Supprimer définitivement ce message ?') }}">{{ __('Supprimer') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <x-audit-history :subject="$record" variant="resume" />

    <x-tn.surface>
        <div class="flex items-center gap-3 border-b border-line pb-4">
            <flux:avatar :name="$record->nom" />
            <div class="min-w-0">
                <p class="font-semibold text-ink">{{ $record->nom }}</p>
                <p class="truncate text-sm text-ink-2">{{ $record->email ?? '—' }}</p>
            </div>
            <span class="ms-auto font-mono text-xs text-ink-2">{{ $record->created_at->format('d.m.Y · H:i') }}</span>
        </div>
        <p class="mt-4 whitespace-pre-line leading-relaxed text-ink">{{ $record->message ?? '—' }}</p>
    </x-tn.surface>

    <x-audit-history :subject="$record" />
</section>
