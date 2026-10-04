<?php

use App\Models\Message;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Messages')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $mine = false;


    public function mount(): void
    {
        $this->authorize('viewAny', Message::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMine(): void
    {
        $this->resetPage();
    }

    /**
     * Recherche et filtres, partagés par la liste (et la carte si l'entité a des coordonnées).
     *
     * @return Builder<Message>
     */
    protected function filteredQuery(): Builder
    {
        return Message::query()
            ->when(! auth()->user()->can('viewAll', Message::class), fn ($query) => $query->whereBelongsTo(auth()->user()))
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($q) => $q->where('nom', 'like', $term)->orWhere('email', 'like', $term)->orWhere('sujet', 'like', $term)->orWhere('message', 'like', $term));
            })
            ->when($this->mine, fn ($query) => $query->whereBelongsTo(auth()->user()));
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with('user')
            ->latest()
            ->paginate(10);
    }

    public function delete(int $id): void
    {
        $record = Message::findOrFail($id);
        $this->authorize('delete', $record);

        $record->delete();

        Flux::toast(variant: 'success', text: __('Message supprimé(e).'));
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        label="{{ __('Contact') }}"
        title="{{ __('Messages') }}"
        :subtitle="__(':n message(s)', ['n' => $this->items->total()])"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Messages' => null]"
    >
        <x-slot:actions>
            @can('create', Message::class)
                <flux:button variant="primary" icon="plus" :href="route('messages.create')" class="tn-cta" wire:navigate>{{ __('Contacter la mairie') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <x-tn.aide id="messages-index">{{ __('Écrivez à un service avec « Nouveau message » ; la réponse apparaîtra dans cette liste.') }}</x-tn.aide>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Rechercher un message…') }}" aria-label="{{ __('Rechercher un message') }}" class="sm:max-w-sm" />
        @can('viewAll', Message::class)
            <flux:checkbox wire:model.live="mine" label="{{ __('Mes messages uniquement') }}" />
        @endcan
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">{{ __('Mise à jour…') }}</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="mail" title="{{ __('Aucun message pour le moment') }}" text="{{ __('Écrivez à la mairie : vos messages envoyés apparaîtront ici.') }}">
            @can('create', Message::class)
                <flux:button variant="primary" icon="plus" :href="route('messages.create')" wire:navigate>{{ __('Contacter la mairie') }}</flux:button>
            @endcan
        </x-tn.empty>
    @else
        <ul>
            @foreach ($this->items as $item)
                <li wire:key="row-{{ $item->id }}" class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-4 border-t border-line py-4">
                    <a href="{{ route('messages.show', $item) }}" wire:navigate class="group grid min-w-0 grid-cols-[40px_minmax(0,1fr)] gap-3">
                        <flux:avatar size="sm" :name="$item->nom" class="mt-0.5" />
                        <span class="min-w-0">
                            <span class="flex flex-wrap items-baseline gap-x-3">
                                <span class="font-semibold text-ink group-hover:text-cyan">{{ $item->sujet ?? __('Sans objet') }}</span>
                                <span class="font-mono text-xs text-ink-2">{{ $item->created_at->format('d.m.Y · H:i') }}</span>
                            </span>
                            <span class="block truncate text-sm text-ink-2">{{ $item->nom }} — {{ \Illuminate\Support\Str::limit((string) $item->message, 120) }}</span>
                        </span>
                    </a>
                    <div class="flex gap-1">
                        @can('update', $item)
                            <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('messages.edit', $item)" wire:navigate aria-label="{{ __('Modifier') }}" />
                        @endcan
                        @can('delete', $item)
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="{{ __('Supprimer ce message ?') }}" aria-label="{{ __('Supprimer') }}" />
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>

        <div>{{ $this->items->links() }}</div>
    @endif
</section>
