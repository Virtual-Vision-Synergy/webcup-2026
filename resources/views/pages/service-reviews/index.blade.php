<?php

use App\Models\ServiceReview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * F76 : « Mes avis sur les services » — l'habitant retrouve ses avis, leur statut et la réponse du service.
 */
new #[Title('Mes avis sur les services')] class extends Component {
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', ServiceReview::class);
    }

    /**
     * Uniquement les avis de l'utilisateur connecté (masqués compris : il voit pourquoi).
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $this->authorize('viewAny', ServiceReview::class);

        return ServiceReview::query()
            ->whereBelongsTo(auth()->user())
            ->with('service:id,nom,slug')
            ->latest('updated_at')
            ->latest('id')
            ->paginate(10);
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        :label="__('Avis des habitants')"
        :title="__('Mes avis sur les services')"
        :subtitle="__('Les avis que vous avez laissés après avoir utilisé un service de la ville.')"
        :breadcrumb="[__('Mon espace') => route('dashboard'), __('Mes avis sur les services') => null]"
    >
        <x-slot:actions>
            <flux:button icon="chat-bubble-bottom-center-text" :href="route('avis.index')" wire:navigate>{{ __('Mes avis sur les projets') }}</flux:button>
        </x-slot:actions>
    </x-tn.page-header>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="star" title="Aucun avis pour le moment" text="Ouvrez la fiche d’un service que vous avez utilisé et cliquez sur « Donner mon avis ».">
            <flux:button variant="primary" icon="building-library" :href="route('services.index')" wire:navigate>{{ __('Voir les services') }}</flux:button>
        </x-tn.empty>
    @else
        <ul class="space-y-3">
            @foreach ($this->items as $item)
                <li wire:key="avis-service-{{ $item->id }}" class="rounded-md border border-line p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <a href="{{ route('services.show', $item->service) }}" wire:navigate class="font-medium text-ink hover:text-cyan">{{ $item->service->nom }}</a>
                        @if ($item->estMasque())
                            <x-tn.status-badge etat="alerte" icon="eye-slash">{{ __('Masqué par la modération') }}</x-tn.status-badge>
                        @else
                            <x-tn.status-badge etat="normal">{{ __('Publié') }}</x-tn.status-badge>
                        @endif
                    </div>
                    <p class="mt-2 text-sm font-semibold text-ink">{{ __('Note') }} : {{ $item->libelleNote() }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-ink-2">{{ $item->comment }}</p>
                    @if ($item->estMasque())
                        <p class="mt-2 text-sm text-magenta">{{ __('Motif du masquage : :motif.', ['motif' => $item->libelleMotifMasquage()]) }}</p>
                    @endif
                    @if ($item->response)
                        <div class="mt-3 border-s-2 border-cyan ps-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-cyan">{{ __('Réponse du service') }}</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-ink">{{ $item->response }}</p>
                        </div>
                    @endif
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                        <p class="font-mono text-xs text-ink-2">
                            {{ __('Envoyé le :date', ['date' => ServiceReview::dateLongue($item->created_at)]) }}
                            @if ($item->updated_at && $item->created_at && $item->updated_at->gt($item->created_at))
                                · {{ __('modifié le :date', ['date' => ServiceReview::dateLongue($item->updated_at)]) }}
                            @endif
                        </p>
                        @can('update', $item)
                            <flux:button size="sm" icon="pencil-square" :href="route('services.reviews.edit', $item->service)" wire:navigate>{{ __('Modifier mon avis') }}</flux:button>
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>

        {{ $this->items->links() }}
    @endif
</section>
