<?php

use App\Models\AlerteCanicule;
use App\Models\Service;
use App\Services\RecommandationProvider;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * F31 : page « Canicule », publique (décision : une information de santé doit être lisible sans compte, comme F46).
 * Alertes en cours par quartier, conseils écrits par profil (sans IA, RecommandationProvider), numéros utiles.
 * Seule action : un habitant connecté enregistre son profil sur SON compte (AlerteCaniculePolicy::choisirProfil).
 */
new #[Layout('layouts::public'), Title('Canicule')] class extends Component {
    /** Profil affiché (« je m'informe pour un proche » : on peut en choisir un autre que le sien). */
    #[Url(as: 'profil')]
    public string $profil = '';

    public function mount(): void
    {
        if (! in_array($this->profil, AlerteCanicule::PROFIL_OPTIONS, true)) {
            $this->profil = auth()->user()?->profil_canicule ?? 'tout_public';
        }
    }

    /**
     * @return Collection<int, AlerteCanicule>
     */
    #[Computed]
    public function alertes(): Collection
    {
        return AlerteCanicule::query()
            ->enCours()
            ->with('quartiers:id,nom')
            ->get()
            ->sortByDesc(fn (AlerteCanicule $alerte): int => $alerte->gravite())
            ->values();
    }

    /**
     * Alerte la plus grave qui touche le quartier de l'habitant connecté.
     */
    #[Computed]
    public function alerteDuQuartier(): ?AlerteCanicule
    {
        return $this->alertes->first(fn (AlerteCanicule $alerte): bool => $alerte->concerne(auth()->user()));
    }

    /**
     * Niveau des conseils : celui du quartier de l'habitant, sinon le plus grave en cours, sinon prévention (vigilance).
     */
    #[Computed]
    public function niveau(): string
    {
        return $this->alerteDuQuartier?->niveau ?? $this->alertes->first()?->niveau ?? 'vigilance';
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function conseils(): array
    {
        $profil = in_array($this->profil, AlerteCanicule::PROFIL_OPTIONS, true) ? $this->profil : 'tout_public';

        return app(RecommandationProvider::class)->conseils($profil, $this->niveau);
    }

    public function enregistrerProfil(): void
    {
        $this->authorize('choisirProfil', AlerteCanicule::class);

        $this->validate(['profil' => ['required', 'in:'.implode(',', AlerteCanicule::PROFIL_OPTIONS)]]);

        // Uniquement le compte connecté : aucun identifiant ne vient du navigateur.
        auth()->user()->forceFill(['profil_canicule' => $this->profil])->save();

        Flux::toast(variant: 'success', text: __('Profil enregistré : vos conseils canicule s’afficheront directement.'));
    }
}; ?>

<div class="mx-auto max-w-4xl space-y-8">
    <header class="space-y-2">
        <flux:heading size="xl" level="1" class="flex items-center gap-2">
            <flux:icon.sun class="size-7 text-amber-500" aria-hidden="true" /> {{ __('Canicule : se protéger de la chaleur') }}
        </flux:heading>
        <flux:text>{{ __('Informations de l’Agence sanitaire de Nova Terra : quartiers touchés, niveau d’alerte et conseils adaptés à chaque personne.') }}</flux:text>
    </header>

    {{-- Alertes en cours --}}
    <section aria-labelledby="canicule-alertes" class="space-y-3">
        <flux:heading size="lg" level="2" id="canicule-alertes">{{ __('Alertes en cours') }}</flux:heading>

        @if ($this->alerteDuQuartier)
            <flux:callout variant="danger" icon="exclamation-triangle" data-test="canicule-mon-quartier">
                <flux:callout.heading>{{ __('Votre quartier est concerné') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Niveau :niveau dans le quartier :quartier. Suivez les conseils ci-dessous.', ['niveau' => $this->alerteDuQuartier->libelleNiveau(), 'quartier' => auth()->user()->quartierResidence?->nom]) }}</flux:callout.text>
            </flux:callout>
        @endif

        @forelse ($this->alertes as $alerte)
            <article wire:key="alerte-{{ $alerte->id }}" class="space-y-2 rounded-md border border-line bg-surface p-4" data-test="canicule-alerte">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge :color="match ($alerte->niveau) { 'urgence' => 'red', 'alerte' => 'orange', default => 'yellow' }">
                        {{ __($alerte->libelleNiveau()) }}
                    </flux:badge>
                    <span class="font-semibold">{{ __('Quartiers : :quartiers', ['quartiers' => $alerte->libelleQuartiers()]) }}</span>
                    @if ($alerte->temperature_max)
                        <span class="text-sm text-ink-2">{{ __('jusqu’à :t °C', ['t' => $alerte->temperature_max]) }}</span>
                    @endif
                </div>
                <p>{{ $alerte->message }}</p>
                <p class="text-xs text-ink-2">
                    {{ __('Du :debut au :fin', ['debut' => $alerte->debut->timezone(\App\Models\Annonce::FUSEAU)->translatedFormat('j F à H:i'), 'fin' => $alerte->fin->timezone(\App\Models\Annonce::FUSEAU)->translatedFormat('j F à H:i')]) }}
                </p>
            </article>
        @empty
            <flux:callout icon="check-circle" variant="success">
                <flux:callout.text>{{ __('Aucune alerte canicule en cours. Voici les conseils de prévention à garder en tête.') }}</flux:callout.text>
            </flux:callout>
        @endforelse
    </section>

    {{-- Conseils par profil --}}
    <section aria-labelledby="canicule-conseils" class="space-y-4">
        <flux:heading size="lg" level="2" id="canicule-conseils">{{ __('Conseils pour moi ou pour un proche') }}</flux:heading>

        <flux:radio.group wire:model.live="profil" variant="segmented" :label="__('Pour qui ?')" class="max-sm:flex-col">
            @foreach (\App\Models\AlerteCanicule::PROFIL_LABELS as $valeur => $libelle)
                <flux:radio :value="$valeur" :label="__($libelle)" />
            @endforeach
        </flux:radio.group>

        <div class="space-y-3 rounded-md border border-line bg-surface p-5" aria-live="polite" data-test="canicule-conseils">
            <flux:heading size="md" level="3">
                {{ __(\App\Models\AlerteCanicule::PROFIL_LABELS[$profil] ?? 'Tout public') }} — {{ __('niveau :niveau', ['niveau' => \App\Models\AlerteCanicule::NIVEAU_LABELS[$this->niveau]]) }}
            </flux:heading>
            <ul class="list-disc space-y-2 ps-5">
                @foreach ($this->conseils as $conseil)
                    <li>{{ $conseil }}</li>
                @endforeach
            </ul>
            <div wire:loading wire:target="profil" class="text-sm text-ink-2">{{ __('Chargement des conseils…') }}</div>
        </div>

        @auth
            @if (auth()->user()->profil_canicule !== $profil)
                <flux:button wire:click="enregistrerProfil" icon="bookmark" size="sm">{{ __('C’est mon profil : l’enregistrer') }}</flux:button>
            @else
                <flux:text class="text-sm">{{ __('C’est le profil enregistré sur votre compte. Vous pouvez en choisir un autre pour vous informer pour un proche.') }}</flux:text>
            @endif
        @endauth
    </section>

    {{-- Numéros utiles (F46) --}}
    <section aria-labelledby="canicule-numeros" class="space-y-3">
        <flux:heading size="lg" level="2" id="canicule-numeros">{{ __('Numéros utiles') }}</flux:heading>
        <ul class="grid gap-3 sm:grid-cols-2">
            @foreach (Service::NUMEROS_URGENCE as $numero)
                <li class="rounded-md bg-surface-2 p-3">
                    <a href="tel:{{ preg_replace('/\s+/', '', $numero['numero']) }}" class="text-xl font-semibold text-cyan">{{ $numero['numero'] }}</a>
                    <p class="font-medium">{{ __($numero['label']) }}</p>
                    <p class="text-sm text-ink-2">{{ __($numero['detail']) }}</p>
                </li>
            @endforeach
        </ul>
        <flux:link :href="route('urgences.index')" wire:navigate>{{ __('Hôpitaux et établissements de santé') }}</flux:link>
    </section>
</div>
