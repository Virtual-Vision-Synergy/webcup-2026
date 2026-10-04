<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\AvailabilitySubscription;
use App\Models\Partner;
use App\Models\PartnerOffering;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Fiche publique d'un service partenaire (F99) : en haut, sur un écran, l'état, la prochaine action, la description,
 * les conditions, les horaires (ouvert / fermé maintenant) et le partenaire. Non publié ou inexistant : 404.
 * « Me prévenir quand disponible » : connecté uniquement (AvailabilitySubscriptionPolicy), requête POST Livewire (CSRF).
 */
new #[Layout('layouts::public'), Title('Service partenaire')] class extends Component {
    use ThrottlesPerUser;

    #[Locked]
    public PartnerOffering $record;

    public function mount(PartnerOffering $partnerOffering): void
    {
        $partnerOffering->loadMissing('partner');

        // PartnerOfferingPolicy::view : un service non publié est introuvable (404) pour le public, plutôt qu'un 403.
        if (Gate::denies('view', $partnerOffering)) {
            abort(404);
        }

        $this->record = $partnerOffering;
    }

    /**
     * Abonnement actif de l'utilisateur connecté (null pour un invité).
     */
    #[Computed]
    public function abonnement(): ?AvailabilitySubscription
    {
        $user = auth()->user();

        return $user === null ? null : AvailabilitySubscription::actifPour($user, $this->record);
    }

    public function subscribe(): void
    {
        if (auth()->guest()) {
            $this->redirectRoute('catalogue.partners.notify', $this->record);

            return;
        }

        $this->authorize('create', [AvailabilitySubscription::class, $this->record]);
        $this->throttlePerUser('me-prevenir', maxAttempts: 10, decaySeconds: 60);

        AvailabilitySubscription::abonner(auth()->user(), $this->record);
        unset($this->abonnement);

        Flux::toast(variant: 'success', text: __('C\'est noté : vous serez prévenu(e) dès que ce service sera de nouveau disponible.'));
    }

    public function unsubscribe(int $id): void
    {
        $subscription = AvailabilitySubscription::findOrFail($id);
        $this->authorize('delete', $subscription);

        $subscription->delete();
        unset($this->abonnement);

        Flux::toast(variant: 'success', text: __('Alerte annulée.'));
    }
}; ?>

@php
    $partner = $record->partner;
    $ouverture = $record->openingStatus();
    $action = $record->prochaineAction();
    $principale = $action['principale'];
    $alternative = $action['alternative'];
    $planning = $record->horaires();
    $aujourdhui = Partner::today();
@endphp

<section class="mx-auto w-full max-w-5xl space-y-5">
    <div class="space-y-3">
        <flux:link :href="auth()->check() ? route('services.index', ['type' => 'partenaires']) : route('partners.show', $partner)" wire:navigate class="text-sm">&larr; {{ auth()->check() ? __('Catalogue des services') : $partner->name }}</flux:link>

        <div class="flex flex-wrap gap-1.5">
            <flux:badge size="sm" color="violet" icon="building-storefront" data-test="badge-partenaire">{{ __('Partenaire') }} · {{ $partner->name }}</flux:badge>
            <flux:badge size="sm" :color="$ouverture['color']" :icon="$ouverture['icon']">{{ $ouverture['label'] }}</flux:badge>
            @unless ($record->is_published)
                <flux:badge size="sm" color="amber" icon="eye-slash">{{ __('Non publié') }}</flux:badge>
            @endunless
        </div>

        <h1 class="tn-display text-[1.75rem] leading-[1.1] font-semibold text-ink md:text-[2.25rem]">{{ __($record->title) }}</h1>

        <x-service-status :service="$record" />
    </div>

    {{-- Prochaine action : UN bouton principal, calculé côté serveur, et une alternative si renseignée. --}}
    <div class="flex flex-col gap-3 rounded-md border border-cyan/40 bg-cyan/5 p-4 sm:flex-row sm:flex-wrap sm:items-center" data-test="prochaine-action" data-action="{{ $principale['type'] }}">
        <span class="text-sm font-medium text-ink">{{ __('Prochaine étape :') }}</span>

        @if ($principale['type'] === 'prevenir')
            @if ($this->abonnement)
                <span class="inline-flex items-center gap-2 font-medium text-green" role="status">
                    <flux:icon name="bell-alert" class="size-5" aria-hidden="true" />{{ __('Vous serez prévenu(e)') }}
                </span>
                <flux:button size="sm" variant="ghost" wire:click="unsubscribe({{ $this->abonnement->id }})">{{ __('Annuler') }}</flux:button>
            @else
                <flux:button variant="primary" icon="bell-alert" wire:click="subscribe">
                    <span wire:loading.remove wire:target="subscribe">{{ __($principale['label']) }}</span>
                    <span wire:loading wire:target="subscribe">{{ __('Enregistrement…') }}</span>
                </flux:button>
                @guest
                    <span class="text-xs text-ink-2">{{ __('Connexion demandée, puis retour sur cette page.') }}</span>
                @endguest
            @endif
        @elseif ($principale['url'])
            <flux:button variant="primary" :icon="$principale['icon']" :href="$principale['url']" :target="$principale['externe'] ? '_blank' : null" :rel="$principale['externe'] ? 'noopener noreferrer' : null">
                {{ __($principale['label']) }}@if ($principale['externe'])<span class="sr-only"> {{ __('(nouvel onglet, site du partenaire)') }}</span>@endif
            </flux:button>
        @endif

        @if ($alternative)
            @if ($alternative['url'])
                <flux:button icon="arrow-uturn-right" :href="$alternative['url']" target="_blank" rel="noopener noreferrer">
                    {{ __($alternative['label']) }}<span class="sr-only"> {{ __('(nouvel onglet)') }}</span>
                </flux:button>
            @endif
            @if ($alternative['texte'])
                <p class="w-full text-sm text-ink-2" data-test="alternative-texte"><span class="font-medium text-ink">{{ __('Alternative :') }}</span> {{ __($alternative['texte']) }}</p>
            @endif
        @endif

        @error('throttle') <p class="w-full text-sm text-magenta">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="space-y-4">
            <flux:card class="space-y-2">
                <flux:heading level="2">{{ __('Description') }}</flux:heading>
                <flux:text class="whitespace-pre-line">{{ __($record->description) }}</flux:text>
                @if ($record->conditions)
                    <flux:heading level="3" class="pt-2">{{ __('Conditions') }}</flux:heading>
                    <flux:text class="whitespace-pre-line">{{ __($record->conditions) }}</flux:text>
                @endif
            </flux:card>

            <flux:card class="space-y-2">
                <flux:heading level="2">{{ __('Contact') }}</flux:heading>
                <dl class="space-y-1 text-sm">
                    @if ($record->contact_phone)
                        <div class="flex gap-2"><dt><flux:icon name="phone" class="size-4" /><span class="sr-only">{{ __('Téléphone') }}</span></dt><dd><a href="{{ $record->contactLink() }}" class="text-cyan hover:underline">{{ $record->contact_phone }}</a></dd></div>
                    @endif
                    @if ($record->contact_email)
                        <div class="flex gap-2"><dt><flux:icon name="envelope" class="size-4" /><span class="sr-only">{{ __('E-mail') }}</span></dt><dd><a href="mailto:{{ $record->contact_email }}" class="break-all text-cyan hover:underline">{{ $record->contact_email }}</a></dd></div>
                    @endif
                    <div class="flex gap-2"><dt><flux:icon name="map-pin" class="size-4" /><span class="sr-only">{{ __('Adresse') }}</span></dt><dd>{{ $partner->address }}</dd></div>
                </dl>
            </flux:card>
        </div>

        <div class="space-y-4">
            <flux:card class="space-y-2">
                <flux:heading level="2">{{ __('Horaires') }}</flux:heading>
                @unless ($record->aDesHorairesPropres())
                    <flux:text class="text-xs">{{ __('Horaires du partenaire.') }}</flux:text>
                @endunless
                <table class="w-full text-sm">
                    <caption class="sr-only">{{ __('Horaires d\'ouverture, heure de Nova Terra') }}</caption>
                    <tbody>
                        @foreach (Partner::JOURS as $jour)
                            <tr @class(['font-semibold bg-cyan/10' => $jour === $aujourdhui]) @if ($jour === $aujourdhui) aria-current="date" @endif>
                                <th scope="row" class="py-1 ps-2 text-start font-medium">
                                    {{ ucfirst($jour) }}@if ($jour === $aujourdhui) <span class="text-xs">({{ __('aujourd\'hui') }})</span>@endif
                                </th>
                                <td class="py-1 pe-2 text-end">{{ $planning->hoursLabel($jour) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </flux:card>

            <flux:card class="space-y-2">
                <flux:heading level="2">{{ __('Proposé par') }}</flux:heading>
                <p class="flex items-center gap-2 font-medium text-ink">
                    <flux:icon :name="Partner::iconeType($partner->type)" class="size-5 text-cyan" aria-hidden="true" />
                    {{ $partner->name }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" icon="building-storefront" :href="route('partners.show', $partner)" wire:navigate>{{ __('Fiche et carte du partenaire') }}</flux:button>
                    <flux:button size="sm" variant="ghost" icon="map" :href="$partner->directionsUrl()" target="_blank" rel="noopener noreferrer">{{ __('Itinéraire') }}</flux:button>
                </div>
            </flux:card>
        </div>
    </div>
</section>
