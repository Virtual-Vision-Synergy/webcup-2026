<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * F30 : notifications de l'utilisateur connecté, non lues en premier.
 * « Marquer comme lue » et « Tout marquer comme lu » sont des formulaires POST vers NotificationController
 * (Policy DatabaseNotificationPolicy : 403 pour la notification d'un autre).
 */
new #[Title('Mes notifications')] class extends Component {
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', DatabaseNotification::class);
    }

    #[Computed]
    public function nonLues(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return auth()->user()->notifications()
            ->reorder()
            ->orderByRaw('read_at is null desc')
            ->latest()
            ->paginate(15);
    }
}; ?>

<section class="mx-auto w-full max-w-4xl space-y-6">
    <x-tn.page-header
        label="Mon espace"
        title="Mes notifications"
        :subtitle="$this->nonLues === 0 ? 'Aucune notification non lue' : $this->nonLues.' notification'.($this->nonLues > 1 ? 's' : '').' non lue'.($this->nonLues > 1 ? 's' : '')"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Notifications' => null]"
    >
        @if ($this->nonLues > 0)
            <x-slot:actions>
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <flux:button type="submit" icon="check" data-test="tout-lire">Tout marquer comme lu</flux:button>
                </form>
            </x-slot:actions>
        @endif
    </x-tn.page-header>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    @if ($this->items->isEmpty())
        <x-tn.empty icon="bell" title="Aucune notification" text="Vous serez prévenu ici dès qu’une annonce importante concernera votre quartier ou toute la ville.">
            <flux:button :href="route('dashboard')" wire:navigate>Retour à mon espace</flux:button>
        </x-tn.empty>
    @else
        <ul class="space-y-3">
            @foreach ($this->items as $notification)
                @php
                    $nonLue = $notification->read_at === null;
                    $niveau = $notification->data['niveau'] ?? null;
                    $etat = match ($niveau) { 'danger', 'alerte' => 'alerte', 'vigilance' => 'perturbe', default => 'info' };
                    $url = $notification->data['url'] ?? null;
                    $urlSure = is_string($url) && preg_match('#^(https?://|/)#i', $url) === 1 && ! str_starts_with($url, '//');
                @endphp
                <li wire:key="notification-{{ $notification->id }}" data-test="notification"
                    @class([
                        'rounded-md border p-4',
                        'border-cyan/50 border-s-4 bg-cyan/5' => $nonLue,
                        'border-line bg-surface opacity-80' => ! $nonLue,
                    ])>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($nonLue)
                            <x-tn.status-badge etat="info">Non lue</x-tn.status-badge>
                        @endif
                        @if (isset($notification->data['niveau_libelle']))
                            <x-tn.status-badge :etat="$etat">{{ $notification->data['niveau_libelle'] }}</x-tn.status-badge>
                        @endif
                        @if (! empty($notification->data['quartier']))
                            <x-tn.status-badge etat="info">Quartier {{ $notification->data['quartier'] }}</x-tn.status-badge>
                        @endif
                        <time datetime="{{ $notification->created_at?->toIso8601String() }}" class="ms-auto font-mono text-xs text-ink-2">
                            {{ $notification->created_at?->diffForHumans() }}
                        </time>
                    </div>

                    <p class="mt-2 font-medium text-ink">{{ $notification->data['sujet'] ?? '' }}</p>
                    @foreach ($notification->data['lignes'] ?? [] as $ligne)
                        <p class="mt-1 text-sm text-ink-2">{{ $ligne }}</p>
                    @endforeach

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if ($urlSure)
                            <flux:button size="sm" variant="primary" :href="$url">{{ $notification->data['libelle'] ?? 'Ouvrir' }}</flux:button>
                        @endif
                        @if ($nonLue)
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                @csrf
                                <flux:button size="sm" variant="ghost" icon="check" type="submit">Marquer comme lue</flux:button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        {{ $this->items->links() }}
    @endif
</section>
