<?php

use App\Models\AvisProjet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * F66 : l'habitant retrouve les avis qu'il a donnés sur les projets de la ville.
 */
new #[Title('Mes avis sur les projets')] class extends Component {
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', AvisProjet::class);
    }

    /**
     * Uniquement les avis de l'utilisateur connecté.
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $this->authorize('viewAny', AvisProjet::class);

        return AvisProjet::query()
            ->whereBelongsTo(auth()->user())
            ->with('projet:id,titre,consultation_ouverte')
            ->latest('updated_at')
            ->latest('id')
            ->paginate(10);
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        label="{{ __('Vie de la ville') }}"
        title="{{ __('Mes avis sur les projets') }}"
        subtitle="{{ __('Les avis que vous avez donnés lors des consultations. Ce ne sont pas des votes officiels.') }}"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Mes avis' => null]"
    >
        <x-slot:actions>
            <flux:button icon="building-office-2" :href="route('projets.index')" wire:navigate>{{ __('Projets de la ville') }}</flux:button>
        </x-slot:actions>
    </x-tn.page-header>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="chat-bubble-bottom-center-text" title="Aucun avis pour le moment" text="Ouvrez un projet en consultation pour donner votre avis : pour, contre ou sans avis.">
            <flux:button variant="primary" icon="building-office-2" :href="route('projets.index')" wire:navigate>{{ __('Voir les projets') }}</flux:button>
        </x-tn.empty>
    @else
        <ul class="space-y-3">
            @foreach ($this->items as $item)
                <li wire:key="avis-{{ $item->id }}" class="rounded-md border border-line p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-tn.status-badge :etat="$item->positionBadge()">{{ __($item->positionLabel()) }}</x-tn.status-badge>
                        @if ($item->projet?->consultation_ouverte)
                            <span class="text-xs text-cyan">{{ __('Consultation ouverte : modifiable') }}</span>
                        @else
                            <span class="text-xs text-ink-2">{{ __('Consultation close') }}</span>
                        @endif
                    </div>
                    @if ($item->projet)
                        <a href="{{ route('projets.show', $item->projet) }}#avis" wire:navigate class="mt-2 block font-medium text-ink hover:text-cyan">{{ $item->projet->titre }}</a>
                    @endif
                    @if ($item->commentaire)
                        <p class="mt-1 whitespace-pre-line text-sm text-ink-2">{{ $item->commentaire }}</p>
                    @endif
                    <p class="mt-2 font-mono text-xs text-ink-2">{{ __('Enregistré le :date', ['date' => $item->enregistreLe()]) }}</p>
                </li>
            @endforeach
        </ul>

        {{ $this->items->links() }}
    @endif
</section>
