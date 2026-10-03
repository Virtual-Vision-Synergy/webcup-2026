<?php

use App\Models\Idea;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * « Mes idées » (F68) : uniquement les idées de l'utilisateur connecté, masquées comprises.
 */
new #[Title('Mes idées')] class extends Component {
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('create', Idea::class);
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return Idea::query()
            ->whereBelongsTo(auth()->user())
            ->withCount('soutiens')
            ->latest()
            ->latest('id')
            ->paginate(10);
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        label="Boîte à idées"
        title="Mes idées"
        subtitle="Suivez l’état de vos idées et la réponse de la ville."
        :breadcrumb="['Boîte à idées' => route('ideas.index'), 'Mes idées' => null]"
    >
        <x-slot:meta>
            <div class="mt-4">
                <flux:button variant="primary" icon="light-bulb" :href="route('ideas.create')" wire:navigate>Proposer une idée</flux:button>
            </div>
        </x-slot:meta>
    </x-tn.page-header>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="light-bulb" title="Vous n’avez pas encore proposé d’idée" text="Une idée pour améliorer la colonie ? Proposez-la en quelques lignes." />
    @else
        <ul class="space-y-3">
            @foreach ($this->items as $item)
                <li wire:key="mon-idee-{{ $item->id }}" class="rounded-md border border-line p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-tn.status-badge :etat="$item->etatStatut()">{{ Idea::libelleStatut($item->status) }}</x-tn.status-badge>
                        <span class="font-mono text-xs text-ink-2">{{ $item->reference }}</span>
                        @if ($item->estMasquee())
                            <x-tn.status-badge etat="alerte">Masquée par la modération</x-tn.status-badge>
                        @endif
                    </div>
                    <a href="{{ route('ideas.show', $item) }}" wire:navigate class="mt-1 block font-medium text-ink hover:text-cyan">{{ $item->title }}</a>
                    <p class="text-sm text-ink-2">
                        {{ $item->soutiens_count }} {{ $item->soutiens_count > 1 ? 'soutiens' : 'soutien' }} ·
                        <span class="font-mono text-xs">Proposée le {{ Idea::dateLocale($item->created_at, 'd/m/Y') }}</span>
                    </p>
                </li>
            @endforeach
        </ul>

        {{ $this->items->links() }}
    @endif
</section>
