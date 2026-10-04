@php
    $actif = \App\Support\ModeAllege::actif();
@endphp

{{--
    F59 : bascule « Mode allégé » (sans images décoratives ni animations) pour les connexions lentes.
    Simple formulaire POST : fonctionne même si les scripts ne sont pas encore chargés. Mémorisé sur le compte (et l'appareil).
--}}
<form method="POST" action="{{ route('mode-allege') }}" {{ $attributes->only('class')->class('inline-flex') }}>
    @csrf
    <button
        type="submit"
        aria-pressed="{{ $actif ? 'true' : 'false' }}"
        aria-label="{{ $actif ? __('Désactiver le mode allégé') : __('Activer le mode allégé (connexion lente)') }}"
        title="{{ $actif ? __('Mode allégé activé : cliquer pour le désactiver') : __('Mode allégé : page plus légère pour les connexions lentes') }}"
        class="inline-flex size-11 shrink-0 items-center justify-center rounded-sm text-ink-2 transition-colors hover:bg-cyan/8 hover:text-ink aria-pressed:bg-cyan/15 aria-pressed:text-ink"
    >
        <flux:icon.bolt class="size-5" />
    </button>
</form>
