<?php

use App\Models\Partner;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Fiche publique d'un partenaire (F74), en un seul écran : statut ouvert / fermé, appeler, itinéraire,
 * horaires de la semaine, adresse, contact et carte. Non publié → 404 (sauf pour les agents et admins).
 */
new #[Layout('layouts::public'), Title('Partenaire')] class extends Component {
    #[Locked]
    public Partner $record;

    public function mount(Partner $partner): void
    {
        // Un brouillon n'existe pas pour le public : 404 plutôt que 403 (ne révèle pas son existence).
        abort_if(Gate::denies('view', $partner), 404);
        $this->authorize('view', $partner);

        $this->record = $partner;
    }
}; ?>

@php
    $statut = $record->openingStatus();
    $aujourdhui = now()->dayOfWeekIso;
@endphp

<section class="w-full space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <flux:link :href="route('partners.index')" wire:navigate class="text-sm">&larr; {{ __('Tous les partenaires') }}</flux:link>
            <h1 class="tn-display mt-1 text-2xl font-semibold text-ink md:text-3xl">{{ $record->name }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <flux:badge size="sm">{{ __($record->typeLabel()) }}</flux:badge>
                <x-tn.status-badge :etat="$statut['open'] ? 'normal' : 'perturbe'">{{ __($statut['label']) }}</x-tn.status-badge>
                @unless ($record->is_published)
                    <flux:badge size="sm" color="amber">{{ __('Non publié') }}</flux:badge>
                @endunless
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button variant="primary" icon="phone" :href="$record->phoneLink()">{{ __('Appeler') }}</flux:button>
            <flux:button icon="arrow-top-right-on-square" :href="$record->directionsUrl()" target="_blank" rel="noopener noreferrer">
                {{ __('Itinéraire') }}<span class="sr-only"> {{ __('(nouvel onglet)') }}</span>
            </flux:button>
            @can('update', $record)
                <flux:button variant="ghost" icon="pencil-square" :href="route('agent.partners.edit', $record)">{{ __('Modifier') }}</flux:button>
            @endcan
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-3 rounded-md border border-line bg-surface p-4 text-sm">
            @if ($record->description)
                <p class="text-ink-2">{{ $record->description }}</p>
            @endif

            <dl class="grid gap-2">
                <div class="flex gap-2"><dt><flux:icon name="map-pin" class="mt-0.5 size-4 text-ink-2" /><span class="sr-only">{{ __('Adresse') }}</span></dt><dd class="text-ink">{{ $record->address }}</dd></div>
                <div class="flex gap-2"><dt><flux:icon name="phone" class="mt-0.5 size-4 text-ink-2" /><span class="sr-only">{{ __('Téléphone') }}</span></dt><dd><a href="{{ $record->phoneLink() }}" class="font-medium text-cyan hover:underline">{{ $record->phone }}</a></dd></div>
                @if ($record->email)
                    <div class="flex gap-2"><dt><flux:icon name="envelope" class="mt-0.5 size-4 text-ink-2" /><span class="sr-only">{{ __('E-mail') }}</span></dt><dd><a href="mailto:{{ $record->email }}" class="text-cyan hover:underline">{{ $record->email }}</a></dd></div>
                @endif
                @if ($record->website)
                    <div class="flex gap-2"><dt><flux:icon name="globe-alt" class="mt-0.5 size-4 text-ink-2" /><span class="sr-only">{{ __('Site web') }}</span></dt><dd class="min-w-0 break-all"><a href="{{ $record->website }}" target="_blank" rel="noopener noreferrer" class="text-cyan hover:underline">{{ $record->website }}</a></dd></div>
                @endif
            </dl>

            <table class="w-full text-left">
                <caption class="mb-1 text-left font-semibold text-ink">{{ __('Horaires de la semaine') }}</caption>
                <tbody class="divide-y divide-line">
                    @foreach (\App\Models\Partner::JOURS as $numero => $jour)
                        <tr @class(['font-semibold text-ink bg-cyan/8' => $numero === $aujourdhui, 'text-ink-2' => $numero !== $aujourdhui])>
                            <th scope="row" class="py-1 pe-3 font-[inherit] capitalize">
                                {{ $jour }}
                                @if ($numero === $aujourdhui)<span class="sr-only">({{ __('aujourd\'hui') }})</span>@endif
                            </th>
                            <td class="py-1 font-mono text-xs">{{ $record->hoursLabel($numero) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-carte
            :points="[['lat' => $record->latitude, 'lng' => $record->longitude, 'titre' => $record->name, 'etat' => $statut['open'] ? 'normal' : 'perturbe', 'lignes' => [$record->address]]]"
            :centre="[(float) $record->latitude, (float) $record->longitude]"
            :zoom="16"
            hauteur="18rem"
            :label="__('Emplacement de :nom', ['nom' => $record->name])"
        />
    </div>
</section>
