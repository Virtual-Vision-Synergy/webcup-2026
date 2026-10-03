@props([
    'niveau' => 'information',
    'titre' => '',
    'contenu' => '',
    'cle' => null,
])

{{--
    Un message général (D18). Le niveau est porté par une couleur, une icône ET un libellé texte.
    Avec une clé : bouton « Fermer » mémorisé dans le navigateur (si le stockage échoue, il se ferme quand même pour la page).
    Sans clé : aperçu (formulaire agent), sans bouton.
--}}
@php
    $style = match ($niveau) {
        'urgent' => ['icone' => 'exclamation-triangle', 'classes' => 'border-magenta/50 bg-magenta/12 text-magenta'],
        'important' => ['icone' => 'exclamation-circle', 'classes' => 'border-amber/50 bg-amber/12 text-amber'],
        default => ['icone' => 'information-circle', 'classes' => 'border-cyan/40 bg-cyan/10 text-cyan'],
    };
    $libelle = \App\Models\Annonce::NIVEAU_LIBELLES[$niveau] ?? 'Information';
@endphp

<div
    {{ $attributes->class(['tn-bandeau-annonce border-b px-4 py-3 lg:px-8', $style['classes']]) }}
    role="{{ $niveau === 'urgent' ? 'alert' : 'status' }}"
    @if ($cle)
        x-data="{ ouvert: true, cle: @js($cle) }"
        x-init="try { ouvert = window.localStorage.getItem(cle) !== '1' } catch (e) {}"
        x-show="ouvert"
    @endif
>
    <div class="mx-auto flex max-w-7xl items-start gap-3">
        <flux:icon :name="$style['icone']" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />

        <div class="min-w-0 flex-1">
            <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="rounded-xs border border-current px-1.5 font-mono text-[10.5px] font-semibold uppercase leading-5 tracking-[.06em]">{{ $libelle }}</span>
                <span class="font-semibold text-ink">{{ $titre }}</span>
            </p>
            <p class="mt-1 text-sm text-ink-2">{{ $contenu }}</p>
        </div>

        @if ($cle)
            <button
                type="button"
                class="-me-2 -mt-1 flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-sm text-ink-2 hover:bg-black/5 hover:text-ink focus-visible:outline-2 dark:hover:bg-white/5"
                aria-label="Fermer le message : {{ $titre }}"
                x-on:click="ouvert = false; try { window.localStorage.setItem(cle, '1') } catch (e) {}"
            >
                <flux:icon.x-mark class="size-5" aria-hidden="true" />
            </button>
        @endif
    </div>
</div>
