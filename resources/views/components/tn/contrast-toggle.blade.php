{{-- Bascule « contraste élevé » (44px). Préférence mémorisée dans le navigateur (localStorage) et appliquée avant l'affichage (voir partials/head). --}}
<button
    type="button"
    x-data="{ on: document.documentElement.classList.contains('hc') }"
    x-on:click="on = ! on; document.documentElement.classList.toggle('hc', on); try { localStorage.setItem('tn.contrast', on ? 'high' : 'normal'); } catch (e) {}"
    x-bind:aria-pressed="on ? 'true' : 'false'"
    x-bind:aria-label="on ? 'Désactiver le contraste élevé' : 'Activer le contraste élevé'"
    aria-label="Contraste élevé"
    title="Contraste élevé"
    {{ $attributes->class('inline-flex size-11 shrink-0 items-center justify-center rounded-sm text-ink-2 transition-colors hover:bg-cyan/8 hover:text-ink aria-pressed:bg-cyan/15 aria-pressed:text-ink') }}
>
    <flux:icon.eye class="size-5" />
</button>
