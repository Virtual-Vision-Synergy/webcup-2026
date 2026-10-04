<?php

use App\Models\Service;
use App\Models\ServiceReview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * F76 : tous les avis publiés (non masqués) d'un service, du plus récent au plus ancien.
 */
new #[Title('Avis des habitants')] class extends Component {
    use WithPagination;

    #[Locked]
    public Service $service;

    public function mount(Service $service): void
    {
        $this->authorize('view', $service);
        $this->service = $service;
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return $this->service->reviews()
            ->visibles()
            ->with('user:id,name')
            ->latest('updated_at')
            ->latest('id')
            ->paginate(10);
    }
}; ?>

<section class="mx-auto w-full max-w-4xl space-y-6">
    <x-tn.page-header
        :label="__('Avis des habitants')"
        :title="$service->nom"
        :breadcrumb="[__('Mon espace') => route('dashboard'), __('Services') => route('services.index'), $service->nom => route('services.show', $service), __('Avis') => null]"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="star" :href="route('services.reviews.edit', $service)" wire:navigate>{{ __('Donner mon avis') }}</flux:button>
        </x-slot:actions>
    </x-tn.page-header>

    <x-service-reviews-summary :service="$service" />

    @if ($this->items->isEmpty())
        <x-tn.empty icon="star" title="Aucun avis pour le moment" text="Soyez le premier à partager votre expérience avec ce service." />
    @else
        <div class="space-y-3">
            @foreach ($this->items as $item)
                <x-service-review :review="$item" wire:key="avis-{{ $item->id }}" />
            @endforeach
        </div>

        {{ $this->items->links() }}
    @endif
</section>
