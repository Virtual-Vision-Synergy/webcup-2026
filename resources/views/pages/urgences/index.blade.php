<?php

use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Urgences / Santé (F46), page publique en lecture seule, décision assumée : en cas d'urgence,
 * l'habitant doit trouver un numéro ou un hôpital sans se connecter. Aucune action, aucune donnée personnelle :
 * uniquement les établissements de santé de l'annuaire public des services.
 */
new #[Layout('layouts::public'), Title('Urgences / Santé')] class extends Component {
    /**
     * Établissements de santé de l'annuaire : ouverts 24 h/24 d'abord, puis par nom.
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function etablissements(): Collection
    {
        return Service::query()
            ->where('categorie', 'sante')
            ->orderByRaw("case when horaires like '%24 h/24%' then 0 else 1 end")
            ->orderBy('nom')
            ->limit(50)
            ->get();
    }

    /**
     * Points de la carte (F45) : nom, adresse, horaires et téléphone.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function points(): array
    {
        return $this->etablissements
            ->filter(fn (Service $service): bool => $service->latitude !== null && $service->longitude !== null)
            ->map(fn (Service $service): array => [
                ...(array) $service->pointCarte(__($service->nom), auth()->check() ? route('services.show', $service) : null),
                'lignes' => array_filter([
                    $service->adresse ? __($service->adresse) : null,
                    $service->horaires ? __($service->horaires) : null,
                    $service->telephone,
                ]),
                'lien' => __('Voir la fiche'),
            ])
            ->values()
            ->all();
    }

    public static function ouvert24h(Service $service): bool
    {
        return str_contains((string) $service->horaires, '24 h/24');
    }

    public static function lienTelephone(string $numero): string
    {
        return 'tel:'.preg_replace('/[^0-9+]/', '', $numero);
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 lg:px-8">
    <x-tn.page-header
        :label="__('Santé')"
        :title="__('Urgences / Santé')"
        :subtitle="__('Numéros d\'urgence, hôpitaux et services de garde de Nova Terra. Touchez un numéro pour appeler.')"
    />

    {{-- NUMÉROS D'URGENCE : visibles sans défiler, un appui pour appeler --}}
    <section aria-labelledby="titre-numeros" class="rounded-md border border-magenta/35 bg-magenta/8 p-4 sm:p-5">
        <h2 id="titre-numeros" class="flex items-center gap-2 font-semibold text-ink">
            <flux:icon name="exclamation-triangle" class="size-5 text-magenta" aria-hidden="true" />
            {{ __('Numéros d\'urgence (gratuits, 24 h/24)') }}
        </h2>
        <ul class="mt-3 grid grid-cols-2 gap-2 lg:grid-cols-4">
            @foreach (Service::NUMEROS_URGENCE as $urgence)
                <li>
                    <a href="{{ $this->lienTelephone($urgence['numero']) }}" class="flex h-full min-h-11 flex-col gap-1 rounded-md border border-line bg-surface p-3 transition-colors hover:border-magenta/50">
                        <span class="flex items-center gap-2 text-sm font-medium text-ink">
                            <flux:icon :name="$urgence['icon']" class="size-4 shrink-0 text-magenta" aria-hidden="true" />
                            {{ __($urgence['label']) }}
                        </span>
                        <span @class(['font-mono font-semibold text-magenta', 'text-2xl' => strlen($urgence['numero']) <= 4, 'text-sm' => strlen($urgence['numero']) > 4])>
                            <span class="sr-only">{{ __('Appeler le') }}</span> {{ $urgence['numero'] }}
                        </span>
                        <span class="text-xs text-ink-2">{{ __($urgence['detail']) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- HÔPITAUX ET SERVICES D'URGENCE --}}
    @if ($this->etablissements->isEmpty())
        <x-tn.empty icon="heart" title="{{ __('Aucun établissement de santé pour le moment') }}" text="{{ __('L\'annuaire est en cours de publication. En cas d\'urgence, appelez l\'un des numéros ci-dessus.') }}" />
    @else
        <div @class(['grid gap-4', 'lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]' => $this->points !== []])>
            <section aria-labelledby="titre-etablissements" class="min-w-0 rounded-md border border-line bg-surface">
                <h2 id="titre-etablissements" class="border-b border-line px-4 py-3 font-semibold text-ink">
                    {{ __('Hôpitaux et services de garde') }}
                    <span class="block text-xs font-normal text-ink-2">{{ __(':n établissement(s), ouverts 24 h/24 en premier', ['n' => $this->etablissements->count()]) }}</span>
                </h2>
                <ul class="divide-y divide-line">
                    @foreach ($this->etablissements as $etablissement)
                        <li wire:key="etablissement-{{ $etablissement->id }}" class="space-y-1.5 px-4 py-3 text-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                @auth
                                    <a href="{{ route('services.show', $etablissement) }}" wire:navigate class="font-semibold text-ink hover:text-cyan hover:underline">{{ __($etablissement->nom) }}</a>
                                @else
                                    <span class="font-semibold text-ink">{{ __($etablissement->nom) }}</span>
                                @endauth
                                @if ($this->ouvert24h($etablissement))
                                    <flux:badge size="sm" color="green">{{ __('24 h/24') }}</flux:badge>
                                @endif
                            </div>
                            @if ($etablissement->adresse)
                                <p class="flex gap-2 text-ink-2"><flux:icon name="map-pin" class="mt-0.5 size-4 shrink-0" /><span><span class="sr-only">{{ __('Adresse :') }}</span> {{ __($etablissement->adresse) }}</span></p>
                            @endif
                            @if ($etablissement->horaires)
                                <p class="flex gap-2 text-ink-2"><flux:icon name="clock" class="mt-0.5 size-4 shrink-0" /><span class="whitespace-pre-line font-mono text-xs leading-5"><span class="sr-only">{{ __('Horaires :') }}</span> {{ __($etablissement->horaires) }}</span></p>
                            @endif
                            <div class="flex flex-wrap gap-x-4 gap-y-1 pt-1">
                                @if ($etablissement->telephone)
                                    <a href="{{ $this->lienTelephone($etablissement->telephone) }}" class="inline-flex min-h-11 items-center gap-1 font-medium text-cyan hover:underline">
                                        <flux:icon name="phone" class="size-4" /><span class="sr-only">{{ __('Appeler') }}</span> {{ $etablissement->telephone }}
                                    </a>
                                @endif
                                @if ($etablissement->latitude !== null && $etablissement->longitude !== null)
                                    <a href="https://www.openstreetmap.org/directions?to={{ $etablissement->latitude }}%2C{{ $etablissement->longitude }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-1 text-cyan hover:underline">
                                        <flux:icon name="arrow-top-right-on-square" class="size-4" />{{ __('Itinéraire') }}<span class="sr-only"> {{ __('vers :nom (nouvel onglet)', ['nom' => __($etablissement->nom)]) }}</span>
                                    </a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            @if ($this->points !== [])
                <div class="min-w-0 lg:sticky lg:top-20 lg:self-start">
                    <x-carte :points="$this->points" hauteur="26rem" :label="__('Carte des hôpitaux et services de garde')" />
                </div>
            @endif
        </div>
    @endif
</section>
