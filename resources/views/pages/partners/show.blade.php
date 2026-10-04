<?php

use App\Models\Partner;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Fiche partenaire (F74), publique : tout tient sur un écran (statut, horaires, contact, carte, itinéraire).
 * Un partenaire non publié n'existe pas pour le public (404), seuls les agents et admins le voient.
 */
new #[Layout('layouts::public'), Title('Partenaire')] class extends Component {
    #[Locked]
    public Partner $record;

    public function mount(Partner $partner): void
    {
        // PartnerPolicy::view : un partenaire non publié est introuvable (404) pour le public, plutôt qu'un 403.
        if (Gate::denies('view', $partner)) {
            abort(404);
        }

        $this->record = $partner;
    }
}; ?>

@php
    $statut = $record->openingStatus();
    $aujourdhui = Partner::today();
@endphp

<section class="mx-auto w-full max-w-5xl space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-1">
            <flux:link :href="route('partners.index')" wire:navigate class="text-sm">&larr; {{ __('Partenaires') }}</flux:link>
            <flux:heading size="xl" level="1">{{ $record->name }}</flux:heading>
            <div class="flex flex-wrap gap-2">
                <flux:badge size="sm" :icon="Partner::iconeType($record->type)">{{ Partner::labelType($record->type) }}</flux:badge>
                <flux:badge size="sm" :color="$statut['color']" :icon="$statut['icon']">{{ $statut['label'] }}</flux:badge>
                @unless ($record->is_published)
                    <flux:badge size="sm" color="amber" icon="eye-slash">{{ __('Non publié') }}</flux:badge>
                @endunless
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button variant="primary" icon="phone" :href="$record->telLink()">{{ __('Appeler') }}</flux:button>
            <flux:button icon="map" :href="$record->directionsUrl()" target="_blank" rel="noopener noreferrer">{{ __('Itinéraire') }}</flux:button>
            @can('update', $record)
                <flux:button variant="ghost" icon="pencil-square" :href="route('agent.partners.edit', $record)">{{ __('Modifier') }}</flux:button>
            @endcan
        </div>
    </div>

    @if ($record->description)
        <flux:text>{{ $record->description }}</flux:text>
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <div class="space-y-4">
            <flux:card class="space-y-2">
                <flux:heading level="2">{{ __('Horaires de la semaine') }}</flux:heading>
                <table class="w-full text-sm">
                    <caption class="sr-only">{{ __('Horaires d\'ouverture, heure de Nova Terra') }}</caption>
                    <tbody>
                        @foreach (Partner::JOURS as $jour)
                            <tr @class(['font-semibold bg-cyan/10' => $jour === $aujourdhui]) @if ($jour === $aujourdhui) aria-current="date" @endif>
                                <th scope="row" class="py-1 ps-2 text-start font-medium">
                                    {{ ucfirst($jour) }}@if ($jour === $aujourdhui) <span class="text-xs">({{ __('aujourd\'hui') }})</span>@endif
                                </th>
                                <td class="py-1 pe-2 text-end">{{ $record->hoursLabel($jour) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </flux:card>

            <flux:card class="space-y-2">
                <flux:heading level="2">{{ __('Contact') }}</flux:heading>
                <dl class="space-y-1 text-sm">
                    <div class="flex gap-2"><dt><flux:icon name="map-pin" class="size-4" /><span class="sr-only">{{ __('Adresse') }}</span></dt><dd>{{ $record->address }}</dd></div>
                    <div class="flex gap-2"><dt><flux:icon name="phone" class="size-4" /><span class="sr-only">{{ __('Téléphone') }}</span></dt><dd><a href="{{ $record->telLink() }}" class="text-cyan hover:underline">{{ $record->phone }}</a></dd></div>
                    @if ($record->email)
                        <div class="flex gap-2"><dt><flux:icon name="envelope" class="size-4" /><span class="sr-only">{{ __('E-mail') }}</span></dt><dd><a href="mailto:{{ $record->email }}" class="text-cyan hover:underline">{{ $record->email }}</a></dd></div>
                    @endif
                    @if ($record->website)
                        <div class="flex gap-2"><dt><flux:icon name="globe-alt" class="size-4" /><span class="sr-only">{{ __('Site web') }}</span></dt><dd><a href="{{ $record->website }}" target="_blank" rel="noopener noreferrer" class="break-all text-cyan hover:underline">{{ $record->website }}</a></dd></div>
                    @endif
                </dl>
            </flux:card>
        </div>

        <x-carte
            :points="[$record->pointCarte($record->name)]"
            :centre="[(float) $record->latitude, (float) $record->longitude]"
            :zoom="16"
            hauteur="22rem"
            :label="'Emplacement : '.$record->name"
        />
    </div>
</section>
