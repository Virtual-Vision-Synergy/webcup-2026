{{--
    Messages généraux (D18) et alertes ciblées (F29) en cours de diffusion, inclus dans tous les gabarits : visibles par tous, invités compris.
    - Les messages fermés (cookie) ne sont plus rendus : rien ne « revient » au changement de page.
    - Un seul message est déplié (le plus important pour ce visiteur) ; les autres sont repliés derrière un bouton.
    - Les alertes d'un quartier qui n'est pas celui du visiteur (ou visiteur sans quartier) sont toujours repliées, en version compacte.
--}}
@php
    $habitant = auth()->user();
    $sansQuartier = $habitant !== null && $habitant->quartier_id === null;
    $fermees = \App\Models\Annonce::clesFermees(request()->cookie(\App\Models\Annonce::COOKIE_FERMES));

    [$principales, $autresQuartiers] = \App\Models\Annonce::enDiffusion($habitant)
        ->reject(fn (\App\Models\Annonce $annonce): bool => in_array($annonce->cleFermeture(), $fermees, true))
        ->partition(fn (\App\Models\Annonce $annonce): bool => ! $annonce->estCiblee() || $annonce->concerne($habitant));

    $visible = $principales->first();
    $repliees = $principales->slice(1)->concat($autresQuartiers)->values();
    $libelleRepliees = match (true) {
        $visible === null => 'Voir les alertes des autres quartiers ('.$repliees->count().')',
        $repliees->count() === 1 => 'Voir l’autre message',
        default => 'Voir les '.$repliees->count().' autres messages',
    };
@endphp

<x-tn.bandeau-mode-degrade />

@if ($visible || $repliees->isNotEmpty())
    <div {{ $attributes->class('tn-bandeaux-annonces') }} x-data="{ tous: false }">
        @foreach (collect([$visible])->filter()->concat($repliees) as $annonce)
            @if ($repliees->isNotEmpty() && $annonce->is($repliees->first()))
                <div id="tn-autres-annonces" x-show="tous" x-cloak>
            @endif

            @php
                $variante = match (true) {
                    $annonce->concerne($habitant) => 'renforce',
                    $annonce->estCiblee() => 'compact',
                    default => 'standard',
                };
            @endphp
            <x-tn.bandeau-annonce
                :variante="$variante"
                :niveau="$annonce->niveau"
                :titre="$annonce->titre"
                :contenu="$annonce->contenu"
                :consignes="$annonce->listeConsignes()"
                :quartier="$annonce->nomQuartier()"
                :lien="$annonce->estCiblee() || $annonce->listeConsignes() !== [] ? route('alertes.show', $annonce->id) : null"
                :cle="$annonce->cleFermeture()"
            />

            @if ($repliees->isNotEmpty() && $loop->last)
                </div>
            @endif
        @endforeach

        @if ($repliees->isNotEmpty())
            <div class="border-b border-line px-4 lg:px-8">
                <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4">
                    <button
                        type="button"
                        class="flex min-h-10 cursor-pointer items-center gap-1 text-sm font-medium text-ink-2 hover:text-ink"
                        aria-controls="tn-autres-annonces"
                        x-bind:aria-expanded="tous ? 'true' : 'false'"
                        x-on:click="tous = ! tous"
                    >
                        <span x-show="! tous">{{ $libelleRepliees }}</span>
                        <span x-show="tous" x-cloak>Masquer</span>
                        <flux:icon.chevron-down class="size-4 transition-transform" x-bind:class="tous && 'rotate-180'" aria-hidden="true" />
                    </button>
                    @if ($sansQuartier && $autresQuartiers->isNotEmpty())
                        <a href="{{ route('profile.edit') }}" class="text-sm font-medium text-ink underline underline-offset-2" wire:navigate>
                            Indiquez votre quartier pour recevoir les alertes qui vous concernent
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endif
