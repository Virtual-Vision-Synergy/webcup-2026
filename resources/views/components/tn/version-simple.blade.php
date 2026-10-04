@php
    $actif = \App\Support\VersionSimple::actif();
@endphp

{{--
    F62 : bouton « Version simple » des pages clés (accueil, services, actualités, mes demandes).
    Simple formulaire POST (sans JavaScript), mémorisé sur le compte et l'appareil.
    Une fois activée, le bandeau des gabarits (x-tn.bandeau-version-simple) permet le retour en un clic.
--}}
@unless ($actif)
    <form method="POST" action="{{ route('version-simple') }}" {{ $attributes->only('class')->class('inline-flex') }}>
        @csrf
        <button
            type="submit"
            data-test="version-simple"
            title="{{ __('Page plus simple et plus rapide : texte, liens et formulaires uniquement') }}"
            class="inline-flex min-h-11 items-center gap-2 rounded-sm border border-line px-3 text-sm font-medium text-ink-2 transition-colors hover:border-cyan/40 hover:text-ink"
        >
            <flux:icon.document-text class="size-4" aria-hidden="true" />
            {{ __('Version simple') }}
        </button>
    </form>
@endunless
