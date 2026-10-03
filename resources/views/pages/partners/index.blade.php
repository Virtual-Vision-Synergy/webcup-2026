<?php

use App\Models\Partner;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Partenaires de la ville (F74), page publique : carte de tous les partenaires, qui est ouvert maintenant,
 * horaires du jour, adresse et contact, filtre par type.
 */
new #[Layout('layouts::public'), Title('Partenaires')] class extends Component {
    #[Url(except: '')]
    public string $type = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Partner::class);
    }

    /**
     * Partenaires publiés (au plus quelques dizaines : pas de pagination, ils sont tous sur la carte).
     *
     * @return Collection<int, Partner>
     */
    #[Computed]
    public function partners(): Collection
    {
        // Type inconnu dans l'URL : ignoré (liste complète) plutôt qu'une erreur.
        $type = in_array($this->type, Partner::TYPE_OPTIONS, true) ? $this->type : null;

        return Partner::query()
            ->published()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->orderBy('name')
            ->limit(100)
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function points(): array
    {
        return $this->partners->map(function (Partner $partner): array {
            $statut = $partner->openingStatus();

            return [
                'lat' => $partner->latitude,
                'lng' => $partner->longitude,
                'titre' => $partner->name,
                'url' => route('partners.show', $partner),
                'etat' => $statut['open'] ? 'normal' : 'perturbe',
                'lignes' => [$statut['label'], $partner->address],
                'lien' => 'Voir la fiche',
            ];
        })->all();
    }
}; ?>

<section class="w-full space-y-6">
    <x-tn.page-header
        :label="__('Partenaires')"
        :title="__('Nos partenaires')"
        :subtitle="__('Horaires, adresse et contact des partenaires de la ville. Voyez d\'un coup d\'œil qui est ouvert maintenant.')"
    />

    <form method="GET" action="{{ route('partners.index') }}" class="flex flex-wrap items-end gap-3">
        <flux:select wire:model.live="type" name="type" :label="__('Type de partenaire')" class="sm:max-w-xs">
            <flux:select.option value="">{{ __('Tous les types') }}</flux:select.option>
            @foreach (\App\Models\Partner::TYPE_LABELS as $valeur => $libelle)
                <flux:select.option :value="$valeur">{{ __($libelle) }}</flux:select.option>
            @endforeach
        </flux:select>
        <noscript><flux:button type="submit">{{ __('Filtrer') }}</flux:button></noscript>
        <span wire:loading class="text-sm text-ink-2">{{ __('Chargement…') }}</span>
    </form>

    @if ($this->partners->isEmpty())
        <x-tn.empty icon="building-storefront" :title="__('Aucun partenaire pour le moment')" :text="__('Aucun partenaire publié ne correspond à ce type. Essayez « Tous les types ».')" />
    @else
        <x-carte :points="$this->points" hauteur="20rem" :label="__('Carte des partenaires')" />

        <ul class="grid gap-4 md:grid-cols-2">
            @foreach ($this->partners as $partner)
                @php($statut = $partner->openingStatus())
                <li wire:key="partner-{{ $partner->id }}" class="flex flex-col gap-2 rounded-md border border-line bg-surface p-4 text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('partners.show', $partner) }}" wire:navigate class="text-base font-semibold text-ink hover:text-cyan hover:underline">{{ $partner->name }}</a>
                        <flux:badge size="sm">{{ __($partner->typeLabel()) }}</flux:badge>
                    </div>
                    <x-tn.status-badge :etat="$statut['open'] ? 'normal' : 'perturbe'">{{ __($statut['label']) }}</x-tn.status-badge>
                    <p class="flex gap-2 text-ink-2"><flux:icon name="clock" class="mt-0.5 size-4 shrink-0" /><span><span class="sr-only">{{ __('Horaires du jour :') }}</span> {{ __('Aujourd\'hui') }} : {{ $partner->hoursLabel(now()->dayOfWeekIso) }}</span></p>
                    <p class="flex gap-2 text-ink-2"><flux:icon name="map-pin" class="mt-0.5 size-4 shrink-0" /><span><span class="sr-only">{{ __('Adresse :') }}</span> {{ $partner->address }}</span></p>
                    <div class="mt-auto flex flex-wrap gap-x-4 gap-y-1">
                        <a href="{{ $partner->phoneLink() }}" class="inline-flex min-h-11 items-center gap-1 font-medium text-cyan hover:underline">
                            <flux:icon name="phone" class="size-4" /><span class="sr-only">{{ __('Appeler') }}</span> {{ $partner->phone }}
                        </a>
                        <a href="{{ $partner->directionsUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-1 text-cyan hover:underline">
                            <flux:icon name="arrow-top-right-on-square" class="size-4" />{{ __('Itinéraire') }}<span class="sr-only"> {{ __('vers :nom (nouvel onglet)', ['nom' => $partner->name]) }}</span>
                        </a>
                        <a href="{{ route('partners.show', $partner) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-cyan hover:underline">{{ __('Fiche complète') }}</a>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
