<?php

use App\Support\Lexique;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Lexique (D13), page publique : décision assumée, ce sont des informations générales,
 * sans donnée personnelle ni action d'écriture, utiles avant même de créer un compte.
 * La recherche ne touche pas la base : elle filtre la liste de App\Support\Lexique.
 */
new #[Layout('layouts::public'), Title('Lexique')] class extends Component {
    public const RECHERCHE_MAX = 50;

    public string $recherche = '';

    /**
     * Recherche limitée à 50 caractères : une saisie plus longue est tronquée, jamais d'erreur.
     */
    public function updatedRecherche(mixed $valeur): void
    {
        $this->recherche = mb_substr(is_string($valeur) ? $valeur : '', 0, self::RECHERCHE_MAX);
    }

    public function effacer(): void
    {
        $this->recherche = '';
    }

    /**
     * @return array<string, array{slug: string, terme: string, definition: string, exemple: string|null, voir_aussi: list<string>, lettre: string}>
     */
    #[Computed]
    public function resultats(): array
    {
        return Lexique::rechercher(mb_substr($this->recherche, 0, self::RECHERCHE_MAX));
    }
}; ?>

@php
    $tous = Lexique::termes();
    $groupes = Lexique::parLettre($this->resultats);
    $nombre = count($this->resultats);
    $saisie = trim($recherche);
@endphp

<section class="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 lg:px-8">
    <x-tn.page-header
        label="Lexique"
        title="Lexique : les mots de la mairie expliqués simplement"
        subtitle="Un mot vous bloque dans une démarche ? Cherchez-le ici : chaque mot est expliqué en une ou deux phrases, avec un exemple."
    />

    <div class="space-y-3">
        <flux:input
            type="search"
            wire:model.live.debounce.300ms="recherche"
            icon="magnifying-glass"
            maxlength="50"
            :label="__('Chercher un mot')"
            :placeholder="__('Ex. CCAS, justificatif, rendez-vous…')"
            data-test="lexique-recherche"
        />

        <p role="status" aria-live="polite" class="text-sm text-ink-2" data-test="lexique-annonce">
            @if ($saisie === '')
                {{ __(':n mots dans le lexique', ['n' => $nombre]) }}
            @elseif ($nombre === 1)
                {{ __('1 mot trouvé pour « :recherche »', ['recherche' => $saisie]) }}
            @else
                {{ __(':n mots trouvés pour « :recherche »', ['n' => $nombre, 'recherche' => $saisie]) }}
            @endif
        </p>
    </div>

    @if ($nombre === 0)
        <x-tn.empty
            icon="book-open"
            title="Aucun mot ne correspond."
            text="Écrivez-nous via la messagerie si un mot vous bloque : nous vous répondrons et l’ajouterons au lexique."
        >
            <div class="flex flex-wrap justify-center gap-2">
                <flux:button variant="ghost" wire:click="effacer">{{ __('Voir tous les mots') }}</flux:button>
                <flux:button variant="primary" :href="route('messages.create')" icon="mail">{{ __('Écrire à la mairie') }}</flux:button>
            </div>
        </x-tn.empty>
    @else
        <nav aria-label="{{ __('Aller à une lettre') }}">
            <ul class="flex flex-wrap gap-2">
                @foreach (array_keys($groupes) as $lettre)
                    <li wire:key="nav-{{ $lettre }}">
                        <a
                            href="#lettre-{{ $lettre }}"
                            class="flex size-11 items-center justify-center rounded-sm border border-line bg-surface font-mono font-semibold text-ink underline-offset-4 hover:border-cyan hover:underline"
                        >
                            <span class="sr-only">{{ __('Lettre') }} </span>{{ $lettre }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="space-y-8">
            @foreach ($groupes as $lettre => $termes)
                <section wire:key="lettre-{{ $lettre }}" aria-labelledby="lettre-{{ $lettre }}" class="scroll-mt-24">
                    <h2 id="lettre-{{ $lettre }}" class="tn-display scroll-mt-24 border-b border-line pb-2 text-2xl font-semibold text-cyan">{{ $lettre }}</h2>

                    <dl class="divide-y divide-line">
                        @foreach ($termes as $terme)
                            <div wire:key="terme-{{ $terme['slug'] }}" class="py-4">
                                <dt id="terme-{{ $terme['slug'] }}" class="scroll-mt-24 text-lg font-semibold text-ink">{{ $terme['terme'] }}</dt>
                                <dd class="mt-1 text-ink">{{ $terme['definition'] }}</dd>
                                @if ($terme['exemple'])
                                    <dd class="mt-2 border-s-2 border-cyan/50 ps-3 text-ink-2">
                                        <span class="font-semibold text-ink">{{ __('Exemple :') }}</span> {{ $terme['exemple'] }}
                                    </dd>
                                @endif
                                @if (count($terme['voir_aussi']))
                                    <dd class="mt-2 text-sm text-ink-2">
                                        <span class="font-semibold text-ink">{{ __('Voir aussi :') }}</span>
                                        @foreach ($terme['voir_aussi'] as $lien)
                                            @if (array_key_exists($lien, $this->resultats))
                                                <a href="#terme-{{ $lien }}" class="text-cyan underline underline-offset-4">{{ $tous[$lien]['terme'] }}</a>@if (! $loop->last), @endif
                                            @else
                                                {{-- Terme masqué par la recherche : on vide la recherche puis on va au terme. --}}
                                                <a
                                                    href="#terme-{{ $lien }}"
                                                    x-on:click.prevent="$wire.effacer().then(() => { const cible = document.getElementById('terme-{{ $lien }}'); cible?.scrollIntoView(); history.replaceState(null, '', '#terme-{{ $lien }}'); })"
                                                    class="text-cyan underline underline-offset-4"
                                                >{{ $tous[$lien]['terme'] }}</a>@if (! $loop->last), @endif
                                            @endif
                                        @endforeach
                                    </dd>
                                @endif
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>
    @endif
</section>
