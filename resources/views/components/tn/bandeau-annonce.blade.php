@props([
    'niveau' => 'information',
    'titre' => '',
    'contenu' => '',
    'consignes' => [],
    'quartier' => null,
    'variante' => 'standard',
    'lien' => null,
    'cle' => null,
    'inviterQuartier' => false,
])

{{--
    Un message général (D18) ou une alerte ciblée (F29). La gravité est portée par une couleur, une icône ET un libellé texte.

    Variantes :
      - standard : message à toute la ville (comportement D18) ;
      - renforce : l'alerte vise le quartier de l'habitant → mention « Concerne votre quartier » ;
                   pour Alerte et Danger, « Replier » remplace « Fermer » : le bandeau reste visible tant que l'alerte est active ;
      - compact  : alerte d'un autre quartier → une ligne, « Quartier X uniquement » et lien vers les consignes.

    Compact par défaut : texte limité à 2 lignes, consignes derrière « Voir plus » ; le lien principal reste toujours visible.

    Avec une clé : logique Alpine `tnBandeau` (resources/js/app.js).
      - « Fermer » est mémorisé dans un cookie lu par le serveur (le message n'est plus rendu ensuite), « Replier » dans le navigateur ;
      - disparition automatique après Annonce::DUREE_AFFICHAGE_SECONDES : même fermeture / même repli, pour la visite seulement ;
        minuteur en pause au survol, au focus clavier et quand le texte est déplié.
    Sans clé : aperçu (formulaire agent, tableau de bord), sans bouton ni minuteur.
    Le rôle alert/status est posé sur le texte seul : le lecteur d'écran n'annonce le titre qu'une fois.
--}}
@php
    $style = match ($niveau) {
        'danger' => ['icone' => 'shield-exclamation', 'fond' => 'border-magenta bg-magenta', 'accent' => 'text-white dark:text-night', 'texte' => 'text-white dark:text-night', 'texte2' => 'text-white/90 dark:text-night/85'],
        'alerte' => ['icone' => 'exclamation-triangle', 'fond' => 'border-magenta/50 bg-magenta/12', 'accent' => 'text-magenta', 'texte' => 'text-ink', 'texte2' => 'text-ink-2'],
        'vigilance' => ['icone' => 'eye', 'fond' => 'border-amber/50 bg-amber/12', 'accent' => 'text-amber', 'texte' => 'text-ink', 'texte2' => 'text-ink-2'],
        default => ['icone' => 'information-circle', 'fond' => 'border-cyan/40 bg-cyan/10', 'accent' => 'text-cyan', 'texte' => 'text-ink', 'texte2' => 'text-ink-2'],
    };
    $libelle = \App\Models\Annonce::NIVEAU_LIBELLES[$niveau] ?? 'Information';
    $grave = in_array($niveau, \App\Models\Annonce::NIVEAUX_GRAVES, true);
    $repliable = $cle && $variante === 'renforce' && $grave;
    $fermable = $cle && ! $repliable;
    $long = $variante !== 'compact' && mb_strlen((string) $contenu) > 140;
    $details = $variante !== 'compact' && (count($consignes) > 0 || $inviterQuartier);
    $voirPlus = $long || $details;
    $idDetails = 'tn-annonce-'.($cle ?? \Illuminate\Support\Str::random(8)).'-details';
    $lienPrincipal = $variante === 'compact' ? 'Voir les consignes' : 'Page de l’alerte (lien à partager)';
    $duree = \App\Models\Annonce::DUREE_AFFICHAGE_SECONDES;
    $bouton = 'flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-sm hover:bg-black/5 focus-visible:outline-2 dark:hover:bg-white/5';
    $lienStyle = 'font-medium underline underline-offset-2 '.$style['texte'];
@endphp

<div
    {{ $attributes->class([
        'tn-bandeau-annonce',
        'relative w-full border-b',
        'border-y-2' => $variante === 'renforce',
        $style['fond'],
    ]) }}
    data-variante="{{ $variante }}"
    @if ($cle)
        data-annonce-cle="{{ $cle }}"
        data-duree-affichage="{{ $duree }}"
        x-data="tnBandeau({ cle: @js($cle), mode: @js($repliable ? 'repliable' : 'fermable'), duree: {{ $duree }}, cookie: @js(\App\Models\Annonce::COOKIE_FERMES) })"
        x-show="ouvert"
        x-collapse
        x-on:mouseenter="survol = true"
        x-on:mouseleave="survol = false"
        x-on:focusin="focus = true"
        x-on:focusout="quitterFocus($event)"
        x-on:tn-bandeaux-reafficher.window="reafficher()"
    @else
        x-data="{ deplie: false }"
    @endif
>
    <div class="mx-auto flex max-w-7xl items-start gap-3 px-4 py-2 sm:px-6 lg:px-8">
        <flux:icon :name="$style['icone']" @class(['mt-0.5 size-5 shrink-0', $style['accent']]) aria-hidden="true" />

        <div class="min-w-0 flex-1" role="{{ $grave ? 'alert' : 'status' }}">
            <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span @class(['rounded-xs border border-current px-1.5 font-mono text-[10.5px] font-semibold uppercase leading-5 tracking-[.06em]', $style['accent']])>{{ $libelle }}</span>
                @if ($variante === 'renforce' && $quartier)
                    <span @class(['rounded-xs px-1.5 font-mono text-[10.5px] font-semibold uppercase leading-5 tracking-[.06em] ring-1 ring-current', $style['accent']])>Concerne votre quartier : {{ $quartier }}</span>
                @elseif ($quartier)
                    <span @class(['font-mono text-[11px] uppercase tracking-[.06em]', $style['texte2']])>Quartier {{ $quartier }} uniquement</span>
                @endif
                <span @class(['font-semibold', $style['texte']])>{{ $titre }}</span>
            </p>

            @if ($variante === 'compact')
                @if ($lien)
                    <a href="{{ $lien }}" @class(['mt-0.5 inline-block text-sm', $lienStyle])>{{ $lienPrincipal }}</a>
                @endif
            @else
                <div @if ($repliable) x-show="! replie" x-collapse @endif>
                    <p @class(['mt-0.5 whitespace-pre-line text-sm', $style['texte2'], 'line-clamp-2' => $long]) @if ($long) x-bind:class="deplie && 'line-clamp-none'" @endif>{{ $contenu }}</p>

                    @if ($details)
                        <div id="{{ $idDetails }}" x-show="deplie" x-cloak>
                            @if (count($consignes))
                                <p @class(['mt-2 text-sm font-semibold', $style['texte']])>Consignes à suivre</p>
                                <ul @class(['mt-1 list-disc space-y-1 ps-5 text-sm', $style['texte']])>
                                    @foreach ($consignes as $consigne)
                                        <li>{{ $consigne }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($inviterQuartier)
                                <p class="mt-2 text-sm">
                                    <a href="{{ route('profile.edit') }}" class="{{ $lienStyle }}">Indiquez votre quartier pour recevoir les alertes qui vous concernent</a>
                                </p>
                            @endif
                        </div>
                    @endif

                    @if ($voirPlus || $lien)
                        <p class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                            @if ($voirPlus)
                                <button
                                    type="button"
                                    class="cursor-pointer {{ $lienStyle }}"
                                    @if ($details) aria-controls="{{ $idDetails }}" @endif
                                    aria-expanded="false"
                                    x-bind:aria-expanded="deplie ? 'true' : 'false'"
                                    x-on:click="deplie = ! deplie"
                                >
                                    <span x-show="! deplie">Voir plus</span>
                                    <span x-show="deplie" x-cloak>Voir moins</span>
                                </button>
                            @endif
                            @if ($lien)
                                <a href="{{ $lien }}" class="{{ $lienStyle }}">{{ $lienPrincipal }}</a>
                            @endif
                        </p>
                    @endif
                </div>
            @endif
        </div>

        @if ($fermable)
            <button
                type="button"
                @class([$bouton, '-me-2 -mt-1', $style['texte2']])
                aria-label="Fermer ce message"
                title="Fermer ce message"
                x-on:click="fermer()"
            >
                <flux:icon.x-mark class="size-5" aria-hidden="true" />
            </button>
        @elseif ($repliable)
            <button
                type="button"
                @class([$bouton, '-me-2 -mt-1 w-auto gap-1 px-2 text-sm font-medium', $style['texte']])
                x-bind:aria-expanded="(! replie).toString()"
                x-on:click="basculerRepli()"
            >
                <span x-text="replie ? 'Déplier' : 'Replier'">Replier</span>
                <span class="sr-only">l’alerte</span>
            </button>
        @endif
    </div>

    @if ($cle)
        {{-- Temps restant avant la disparition automatique (décoratif). --}}
        <div class="absolute inset-x-0 bottom-0 h-0.5" aria-hidden="true" x-show="minuteur !== null" x-cloak>
            <div @class(['h-full origin-left bg-current opacity-40 transition-transform duration-100 ease-linear rtl:origin-right', $style['accent']]) x-bind:style="'transform: scaleX(' + progression + ')'"></div>
        </div>
    @endif
</div>
