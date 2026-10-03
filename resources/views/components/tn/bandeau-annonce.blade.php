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
      - standard : message à toute la ville (comportement D18), consignes en liste s'il y en a ;
      - renforce : l'alerte vise le quartier de l'habitant → consignes affichées, mention « Concerne votre quartier » ;
                   pour Alerte et Danger, « Replier » remplace « Fermer » : le bandeau reste visible tant que l'alerte est active ;
      - compact  : alerte d'un autre quartier → une ligne, « Quartier X uniquement » et lien vers les consignes.

    Avec une clé : bouton mémorisé dans le navigateur (si le stockage échoue, il agit quand même pour la page).
    Sans clé : aperçu (formulaire agent, tableau de bord), sans bouton.
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
    $bouton = 'flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-sm hover:bg-black/5 focus-visible:outline-2 dark:hover:bg-white/5';
@endphp

<div
    {{ $attributes->class([
        'tn-bandeau-annonce',
        'border-b px-4 lg:px-8',
        $variante === 'renforce' ? 'border-y-2 py-4' : ($variante === 'compact' ? 'py-2' : 'py-3'),
        $style['fond'],
    ]) }}
    role="{{ $grave ? 'alert' : 'status' }}"
    data-variante="{{ $variante }}"
    @if ($fermable)
        x-data="{ ouvert: true, cle: @js($cle) }"
        x-init="try { ouvert = window.localStorage.getItem(cle) !== '1' } catch (e) {}"
        x-show="ouvert"
    @elseif ($repliable)
        x-data="{ replie: false, cle: @js($cle.'.replie') }"
        x-init="try { replie = window.localStorage.getItem(cle) === '1' } catch (e) {}"
    @endif
>
    <div class="mx-auto flex max-w-7xl items-start gap-3">
        <flux:icon :name="$style['icone']" @class(['mt-0.5 shrink-0', $style['accent'], $variante === 'renforce' ? 'size-6' : 'size-5']) aria-hidden="true" />

        <div class="min-w-0 flex-1">
            <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span @class(['rounded-xs border border-current px-1.5 font-mono text-[10.5px] font-semibold uppercase leading-5 tracking-[.06em]', $style['accent']])>{{ $libelle }}</span>
                @if ($variante === 'renforce' && $quartier)
                    <span @class(['rounded-xs px-1.5 font-mono text-[10.5px] font-semibold uppercase leading-5 tracking-[.06em] ring-1 ring-current', $style['accent']])>Concerne votre quartier : {{ $quartier }}</span>
                @elseif ($quartier)
                    <span @class(['font-mono text-[11px] uppercase tracking-[.06em]', $style['texte2']])>Quartier {{ $quartier }} uniquement</span>
                @endif
                <span @class(['font-semibold', $style['texte'], 'text-lg' => $variante === 'renforce'])>{{ $titre }}</span>
            </p>

            @if ($variante === 'compact')
                @if ($lien)
                    <a href="{{ $lien }}" @class(['mt-0.5 inline-block text-sm font-medium underline underline-offset-2', $style['texte']])>Voir les consignes</a>
                @endif
            @else
                <div @if ($repliable) x-show="! replie" @endif>
                    <p @class(['mt-1 whitespace-pre-line text-sm', $style['texte2']])>{{ $contenu }}</p>

                    @if (count($consignes))
                        <p @class(['mt-3 text-sm font-semibold', $style['texte']])>Consignes à suivre</p>
                        <ul @class(['mt-1 list-disc space-y-1 ps-5 text-sm', $style['texte']])>
                            @foreach ($consignes as $consigne)
                                <li>{{ $consigne }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($lien || $inviterQuartier)
                        <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                            @if ($lien)
                                <a href="{{ $lien }}" @class(['font-medium underline underline-offset-2', $style['texte']])>Page de l’alerte (lien à partager)</a>
                            @endif
                            @if ($inviterQuartier)
                                <a href="{{ route('profile.edit') }}" @class(['font-medium underline underline-offset-2', $style['texte']])>Indiquez votre quartier pour recevoir les alertes qui vous concernent</a>
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
                aria-label="Fermer le message : {{ $titre }}"
                x-on:click="ouvert = false; try { window.localStorage.setItem(cle, '1') } catch (e) {}"
            >
                <flux:icon.x-mark class="size-5" aria-hidden="true" />
            </button>
        @elseif ($repliable)
            <button
                type="button"
                @class([$bouton, '-me-2 -mt-1 w-auto gap-1 px-2 text-sm font-medium', $style['texte']])
                x-bind:aria-expanded="(! replie).toString()"
                x-on:click="replie = ! replie; try { window.localStorage.setItem(cle, replie ? '1' : '0') } catch (e) {}"
            >
                <span x-text="replie ? 'Déplier' : 'Replier'">Replier</span>
                <span class="sr-only">l’alerte : {{ $titre }}</span>
            </button>
        @endif
    </div>
</div>
