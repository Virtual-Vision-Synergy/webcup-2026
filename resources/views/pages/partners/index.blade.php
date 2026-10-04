<?php

use App\Models\Partner;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Partenaires (F74), page publique en lecture seule, décision assumée : l'habitant voit sans compte
 * qui est ouvert maintenant, les horaires du jour et où se rendre. Seuls les partenaires publiés apparaissent.
 */
new #[Layout('layouts::public'), Title('Partenaires')] class extends Component {
    #[Url(except: '')]
    public string $type = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Partner::class);
    }

    /**
     * @return Collection<int, Partner>
     */
    #[Computed]
    public function partners(): Collection
    {
        return Partner::query()
            ->published()
            ->when(in_array($this->type, Partner::TYPE_OPTIONS, true), fn ($query) => $query->where('type', $this->type))
            ->orderBy('name')
            ->limit(100)
            ->get();
    }

    /**
     * Points de la carte (F45) : statut, horaires du jour, adresse et téléphone.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function points(): array
    {
        $jour = Partner::today();

        return $this->partners
            ->map(fn (Partner $partner): array => [
                ...(array) $partner->pointCarte($partner->name, route('partners.show', $partner)),
                'lignes' => [
                    $partner->openingStatus()['label'],
                    'Aujourd\'hui : '.$partner->hoursLabel($jour),
                    $partner->address,
                    $partner->phone,
                ],
                'lien' => 'Voir la fiche',
            ])
            ->values()
            ->all();
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    <div class="space-y-1">
        <flux:heading size="xl" level="1">{{ __('Partenaires') }}</flux:heading>
        <flux:text>{{ __('Horaires, adresse et contact de nos partenaires. Le statut « ouvert / fermé » est à l\'heure de Nova Terra.') }}</flux:text>
    </div>

    <x-carte :points="$this->points" hauteur="18rem" label="Carte des partenaires" />

    <div class="flex flex-wrap items-end gap-3">
        <flux:select wire:model.live="type" :label="__('Type de partenaire')" class="max-w-xs">
            <flux:select.option value="">{{ __('Tous les types') }}</flux:select.option>
            @foreach (Partner::TYPE_LABELS as $valeur => $libelle)
                <flux:select.option :value="$valeur">{{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>
        <span wire:loading class="text-sm text-ink-2">{{ __('Chargement…') }}</span>
    </div>

    @if ($this->partners->isEmpty())
        <flux:card class="text-center">
            <flux:heading>{{ __('Aucun partenaire pour ce type.') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Essayez un autre type ou affichez tous les partenaires.') }}</flux:text>
            @if ($type !== '')
                <flux:button class="mt-3" wire:click="$set('type', '')">{{ __('Voir tous les partenaires') }}</flux:button>
            @endif
        </flux:card>
    @else
        <ul class="grid gap-4 md:grid-cols-2">
            @foreach ($this->partners as $partner)
                @php($statut = $partner->openingStatus())
                <li wire:key="partner-{{ $partner->id }}">
                    <flux:card class="h-full space-y-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <flux:heading level="2">
                                    <a href="{{ route('partners.show', $partner) }}" wire:navigate class="hover:underline">{{ $partner->name }}</a>
                                </flux:heading>
                                <flux:badge size="sm" :icon="Partner::iconeType($partner->type)" class="mt-1">{{ Partner::labelType($partner->type) }}</flux:badge>
                            </div>
                            <flux:badge size="sm" :color="$statut['color']" :icon="$statut['icon']">{{ $statut['label'] }}</flux:badge>
                        </div>

                        <dl class="space-y-1 text-sm">
                            <div class="flex gap-2"><dt class="font-medium">{{ __('Aujourd\'hui :') }}</dt><dd>{{ $partner->hoursLabel(Partner::today()) }}</dd></div>
                            <div class="flex gap-2"><dt class="sr-only">{{ __('Adresse') }}</dt><dd>{{ $partner->address }}</dd></div>
                        </dl>

                        <div class="flex flex-wrap gap-2">
                            <flux:button size="sm" icon="phone" :href="$partner->telLink()">{{ $partner->phone }}</flux:button>
                            <flux:button size="sm" icon="map" :href="$partner->directionsUrl()" target="_blank" rel="noopener noreferrer">{{ __('Itinéraire') }}</flux:button>
                            <flux:button size="sm" variant="ghost" :href="route('partners.show', $partner)" wire:navigate>{{ __('Voir la fiche') }}</flux:button>
                        </div>
                    </flux:card>
                </li>
            @endforeach
        </ul>
    @endif
</section>
