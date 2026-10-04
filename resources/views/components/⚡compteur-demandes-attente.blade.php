<?php

use App\Models\Demarche;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
| D17 : nombre de demandes des habitants en attente de prise en charge (tableau de bord agent).
| Calculé en base à chaque rendu (pas de cache) : il suit chaque changement d'état.
| F70 : limité aux services de l'agent (tous pour l'admin).
*/
new class extends Component {
    public function mount(): void
    {
        Gate::authorize('viewAgentSpace');
    }

    #[Computed]
    public function total(): int
    {
        Gate::authorize('viewAgentSpace');

        return Demarche::query()->visibleTo(auth()->user())->awaitingHandling()->count();
    }

    /**
     * Date de dépôt de la plus ancienne demande encore en attente.
     */
    #[Computed]
    public function plusAncienne(): ?Carbon
    {
        Gate::authorize('viewAgentSpace');

        $date = Demarche::query()->visibleTo(auth()->user())->awaitingHandling()->min('created_at');

        return $date ? Carbon::parse($date) : null;
    }
}; ?>

<div wire:poll.{{ \App\Support\ModeDegrade::poll(60) }}.visible data-test="compteur-demandes-attente">
    <div @class([
        'flex flex-col gap-4 rounded-md border p-5 sm:flex-row sm:items-center sm:justify-between',
        'border-amber/50 bg-amber/5' => $this->total > 0,
        'border-line' => $this->total === 0,
    ])>
        <div class="flex items-center gap-4" role="status" aria-live="polite">
            @if ($this->total > 0)
                <flux:icon.inbox-stack class="size-10 shrink-0 text-amber" aria-hidden="true" />
                <p>
                    <span class="tn-display block text-5xl font-semibold leading-none text-ink">{{ $this->total }}</span>
                    <span class="mt-1 block font-medium text-ink">
                        {{ $this->total === 1 ? 'demande en attente de prise en charge' : 'demandes en attente de prise en charge' }}
                    </span>
                    @if ($this->plusAncienne)
                        <span class="block text-sm text-ink-2">La plus ancienne a été déposée {{ $this->plusAncienne->diffForHumans() }}.</span>
                    @endif
                </p>
            @else
                <flux:icon.check-circle class="size-10 shrink-0 text-green" aria-hidden="true" />
                <p>
                    <span class="tn-display block text-5xl font-semibold leading-none text-ink">0</span>
                    <span class="mt-1 block font-medium text-ink">Aucune demande en attente</span>
                    <span class="block text-sm text-ink-2">Toutes les demandes des habitants ont été prises en charge.</span>
                </p>
            @endif
        </div>

        @if ($this->total > 0)
            <flux:button :href="route('agent.demandes', ['filterStatut' => 'deposee'])" wire:navigate icon-trailing="arrow-right" class="shrink-0">
                Voir ces demandes
            </flux:button>
        @endif
    </div>
</div>
