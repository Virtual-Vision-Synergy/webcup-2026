<?php

use App\Models\Consultation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * F65 : consultations des habitants. L'habitant voit celles qui le concernent (toute la ville ou son quartier)
 * et sa trace de participation ; les agents et les admins voient toutes les consultations et le nombre de participants.
 */
new #[Title('Consultations')] class extends Component {
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', Consultation::class);
    }

    #[Computed]
    public function gere(): bool
    {
        return auth()->user()->can('create', Consultation::class);
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $this->authorize('viewAny', Consultation::class);
        $user = auth()->user();

        return Consultation::query()
            ->with('quartier:id,nom')
            ->withCount('participations')
            ->when(! $this->gere, fn ($query) => $query->pourHabitant($user)->where('ouverture_le', '<=', now()))
            ->with(['participations' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderByDesc('cloture_le')
            ->paginate(10);
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        label="{{ __('Vie de la ville') }}"
        title="{{ __('Consultations') }}"
        subtitle="{{ __('La ville vous demande votre avis avant certaines décisions. Vous répondez une fois ; à la clôture, les résultats et la décision prise vous sont publiés.') }}"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Consultations' => null]"
    >
        @if ($this->gere)
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" :href="route('consultations.create')" wire:navigate>{{ __('Nouvelle consultation') }}</flux:button>
            </x-slot:actions>
        @endif
    </x-tn.page-header>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="chat-bubble-left-right" title="Aucune consultation pour le moment" text="Quand la ville vous demandera votre avis, la consultation apparaîtra ici et en haut de chaque page.">
            @if ($this->gere)
                <flux:button variant="primary" icon="plus" :href="route('consultations.create')" wire:navigate>{{ __('Créer une consultation') }}</flux:button>
            @endif
        </x-tn.empty>
    @else
        <ul class="space-y-3">
            @foreach ($this->items as $item)
                @php($participation = $item->participations->first())
                <li wire:key="consultation-{{ $item->id }}" class="rounded-md border border-line p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-tn.status-badge :etat="$item->statutBadge()">{{ $item->statutLabel() }}</x-tn.status-badge>
                        <span class="text-xs text-ink-2">{{ $item->nomPublic() }}</span>
                        @if ($item->decision)
                            <x-tn.status-badge etat="normal" icon="check-badge">{{ __('Décision publiée') }}</x-tn.status-badge>
                        @endif
                    </div>
                    <a href="{{ route('consultations.show', $item) }}" wire:navigate class="mt-2 block font-medium text-ink hover:text-cyan">{{ $item->question }}</a>
                    <p class="mt-1 font-mono text-xs text-ink-2">
                        {{ __('Du :debut au :fin', ['debut' => $item->ouvertureLocale(), 'fin' => $item->clotureLocale()]) }}
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                        @if ($this->gere)
                            <span class="font-mono text-ink">{{ trans_choice(':count participant|:count participants', $item->participations_count, ['count' => $item->participations_count]) }}</span>
                        @elseif ($participation)
                            <span class="inline-flex items-center gap-1 text-green">
                                <flux:icon.check-circle variant="micro" aria-hidden="true" />
                                {{ __('Vous avez participé le :date', ['date' => $participation->participeLe()]) }}
                            </span>
                        @elseif ($item->estOuverte())
                            <flux:link :href="route('consultations.show', $item)" wire:navigate>{{ __('Donner mon avis') }}</flux:link>
                        @else
                            <span class="text-ink-2">{{ __('Vous n’avez pas participé.') }}</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        {{ $this->items->links() }}
    @endif
</section>
