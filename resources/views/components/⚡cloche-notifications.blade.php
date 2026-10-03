<?php

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function items(): Collection
    {
        return auth()->user()->notifications()->latest()->take(5)->get();
    }

    public function markAsRead(string $id): void
    {
        // Recherche limitée aux notifications de l'utilisateur connecté : l'ID d'un autre donne 404.
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        unset($this->unreadCount, $this->items);
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        unset($this->unreadCount, $this->items);
    }
}; ?>

<div x-data="{ ouvert: false }" x-on:keydown.escape.window="ouvert = false">
    <div>
        <flux:sidebar.item icon="bell" x-on:click="ouvert = ! ouvert" data-test="cloche-notifications"
            :aria-label="$this->unreadCount === 0 ? 'Notifications, aucune non lue' : 'Notifications, '.$this->unreadCount.' non lue'.($this->unreadCount > 1 ? 's' : '')">
            {{ __('Notifications') }}
            @if ($this->unreadCount > 0)
                <flux:badge size="sm" color="red" class="ms-1" data-test="cloche-compteur">{{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}</flux:badge>
            @endif
        </flux:sidebar.item>

        <div x-show="ouvert" x-cloak x-on:click.outside="ouvert = false" class="fixed bottom-20 start-3 z-50 w-80 max-w-[calc(100vw-1.5rem)] rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-center justify-between gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-700">
                <flux:heading size="sm">{{ __('Notifications') }}</flux:heading>
                @if ($this->unreadCount > 0)
                    <flux:button size="xs" variant="ghost" wire:click="markAllAsRead" wire:loading.attr="disabled">
                        {{ __('Mark all as read') }}
                    </flux:button>
                @endif
            </div>

            <div class="max-h-96 divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-700">
                @forelse ($this->items as $notification)
                    @php
                        $data = (array) $notification->data;
                        $url = $data['url'] ?? null;
                        $urlSure = is_string($url) && preg_match('#^(https?://|/)#i', $url) === 1 && ! str_starts_with($url, '//');
                        $sujet = $data['sujet'] ?? '';
                        $lignes = $data['lignes'] ?? [];
                        // F49 : avis d'état d'une demande, retraduit dans la langue courante et ouvert par notifications.open.
                        if ($notification->type === \App\Notifications\StatutDemandeChange::class && \App\Notifications\StatutDemandeChange::estAvisDeStatut($data)) {
                            $sujet = \App\Notifications\StatutDemandeChange::sujetDepuis($data);
                            $lignes = [\App\Notifications\StatutDemandeChange::ligneQuoiFaire((string) $data['demande_type'], (string) ($data['statut_apres'] ?? ''))];
                            $url = route('notifications.open', $notification->id);
                            $urlSure = true;
                            $data['libelle'] = __('Voir ma demande');
                        }
                    @endphp
                    <div wire:key="notification-{{ $notification->id }}" class="space-y-1 px-3 py-2 {{ $notification->read_at ? 'opacity-60' : '' }}">
                        <div class="flex items-start justify-between gap-2">
                            <flux:heading size="sm">{{ $sujet }}</flux:heading>
                            @if (! $notification->read_at)
                                <flux:button size="xs" variant="ghost" icon="check" inset wire:click="markAsRead('{{ $notification->id }}')" :aria-label="__('Mark as read')" />
                            @endif
                        </div>
                        @foreach ($lignes as $ligne)
                            <flux:text size="sm">{{ $ligne }}</flux:text>
                        @endforeach
                        @if ($urlSure)
                            <flux:link :href="$url" class="text-sm">{{ $data['libelle'] ?? __('Open') }}</flux:link>
                        @endif
                        <flux:text size="xs" class="text-zinc-500">{{ $notification->created_at?->diffForHumans() }}</flux:text>
                    </div>
                @empty
                    <flux:text class="px-3 py-4 text-center">{{ __('No notifications') }}</flux:text>
                @endforelse
            </div>

            <div class="border-t border-zinc-200 px-3 py-2 text-center dark:border-zinc-700">
                <flux:link :href="route('notifications.index')" wire:navigate class="text-sm">Voir toutes mes notifications</flux:link>
            </div>
        </div>
    </div>
</div>
