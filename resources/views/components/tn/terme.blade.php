@props(['slug'])

@php
    // D13 : infobulle accessible. Bouton « ? » (disclosure) qui ouvre la définition dans le flux du texte.
    $definition = \App\Support\Lexique::terme($slug);

    if ($definition === null && app()->isLocal()) {
        logger()->warning('Lexique : terme inconnu dans <x-tn.terme>.', ['slug' => $slug]);
    }

    $uid = 'def-'.$slug.'-'.\Illuminate\Support\Str::random(6);
    $libelle = trim(strip_tags((string) $slot)) ?: ($definition['terme'] ?? $slug);
@endphp

@if ($definition === null)
    <span {{ $attributes }}>{{ $slot }}</span>
@else
    <span
        {{ $attributes->class('tn-terme') }}
        x-data="{ ouvert: false }"
        x-on:keydown.escape="if (ouvert) { ouvert = false; $refs.bouton.focus(); }"
    ><span class="underline decoration-dotted decoration-2 underline-offset-4">{{ $slot }}</span><button
            type="button"
            x-ref="bouton"
            x-on:click="ouvert = ! ouvert"
            x-bind:aria-expanded="ouvert ? 'true' : 'false'"
            aria-expanded="false"
            aria-controls="{{ $uid }}"
            class="ms-1 inline-flex size-6 translate-y-[-1px] items-center justify-center rounded-full border border-cyan/50 align-middle font-mono text-xs font-semibold text-cyan hover:bg-cyan/10"
            data-test="terme-{{ $slug }}"
        ><span aria-hidden="true">?</span><span class="sr-only">{{ __('Que veut dire :terme ?', ['terme' => $libelle]) }}</span></button><span
            id="{{ $uid }}"
            role="note"
            x-show="ouvert"
            style="display: none"
            class="my-2 block max-w-prose rounded-md border border-line border-s-4 border-s-cyan bg-surface-2 p-3 text-start text-sm font-normal text-ink"
        >
            <span class="block font-semibold">{{ $definition['terme'] }}</span>
            <span class="mt-1 block">{{ $definition['definition'] }}</span>
            <a href="{{ route('lexique') }}#terme-{{ $definition['slug'] }}" class="mt-2 inline-block text-cyan underline underline-offset-4">{{ __('Voir dans le lexique') }}</a>
        </span></span>
@endif
