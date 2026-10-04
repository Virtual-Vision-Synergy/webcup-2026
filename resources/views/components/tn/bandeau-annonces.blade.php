{{--
    Messages généraux (D18) et alertes ciblées (F29) en cours de diffusion, inclus dans tous les gabarits : visibles par tous, invités compris.
    - Les messages fermés (cookie) ne sont plus rendus : rien ne « revient » au changement de page.
    - Un seul message est déplié (le plus important pour ce visiteur) ; les autres sont repliés derrière un bouton.
    - Les alertes d'un quartier qui n'est pas celui du visiteur (ou visiteur sans quartier) sont toujours repliées, en version compacte.
    - F73 : les messages officiels du Haut Conseil passent avant tout le reste et ne sont jamais repliés. Une fois masqué,
      un message officiel laisse une ligne « Relire » vers sa page : il reste consultable pendant toute sa diffusion.
    - Chaque bandeau disparaît seul après quelques secondes pour la visite (voir tn.bandeau-annonce) ; « Afficher » les rouvre.
    - Pleine largeur même si un parent devient une grille (grille Flux de <flux:main> : le bandeau y formait une colonne étroite à gauche).
--}}
@php
    $habitant = auth()->user();
    $sansQuartier = $habitant !== null && $habitant->isCitoyen() && $habitant->quartier_id === null;
    $fermees = \App\Models\Annonce::clesFermees(request()->cookie(\App\Models\Annonce::COOKIE_FERMES));

    [$officiels, $ordinaires] = \App\Models\Annonce::enDiffusion($habitant)
        ->partition(fn (\App\Models\Annonce $annonce): bool => $annonce->estOfficiel());
    [$officielsMasques, $officielsVisibles] = $officiels
        ->partition(fn (\App\Models\Annonce $annonce): bool => in_array($annonce->cleFermeture(), $fermees, true));

    [$principales, $autresQuartiers] = $ordinaires
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
<x-tn.bandeau-dependances />

@if ($officiels->isNotEmpty() || $visible || $repliees->isNotEmpty())
    <div {{ $attributes->class('tn-bandeaux-annonces w-full [grid-column:1/-1]') }} x-data="{ tous: false }">
        @foreach ($officielsVisibles as $annonce)
            <x-tn.bandeau-annonce
                officiel
                :variante="$annonce->concerne($habitant) ? 'renforce' : 'standard'"
                :niveau="$annonce->niveau"
                :titre="$annonce->titre"
                :contenu="$annonce->contenu"
                :consignes="$annonce->listeConsignes()"
                :quartier="$annonce->nomQuartier()"
                :date="$annonce->debut"
                :lien="route('alertes.show', $annonce->id)"
                :cle="$annonce->cleFermeture()"
            />
        @endforeach

        @foreach ($officielsMasques as $annonce)
            <div class="border-b-2 border-ink bg-surface">
                <p class="mx-auto flex min-h-10 max-w-7xl flex-wrap items-center gap-x-2 px-4 text-sm text-ink sm:px-6 lg:px-8">
                    <flux:icon.building-library class="size-4 shrink-0" aria-hidden="true" />
                    <span class="font-semibold">Message officiel du Haut Conseil :</span>
                    <span class="min-w-0 truncate">{{ $annonce->titre }}</span>
                    <a href="{{ route('alertes.show', $annonce->id) }}" class="font-medium underline underline-offset-2">Relire</a>
                </p>
            </div>
        @endforeach

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

        {{-- Messages masqués automatiquement pendant la visite : un clic les rouvre. --}}
        <div class="border-b border-line" x-show="$store.tnBandeaux?.masques.length > 0" x-cloak>
            <div class="mx-auto flex max-w-7xl items-center gap-2 px-4 sm:px-6 lg:px-8">
                <flux:icon.bell class="size-4 shrink-0 text-ink-2" aria-hidden="true" />
                <button type="button" class="flex min-h-10 cursor-pointer items-center text-sm font-medium text-ink-2 hover:text-ink" x-on:click="$store.tnBandeaux.reafficher()">
                    <span x-text="$store.tnBandeaux?.masques.length === 1 ? '1 message masqué · Afficher' : ($store.tnBandeaux?.masques.length + ' messages masqués · Afficher')"></span>
                </button>
            </div>
        </div>

        @if ($repliees->isNotEmpty())
            <div class="border-b border-line">
                <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 px-4 sm:px-6 lg:px-8">
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
