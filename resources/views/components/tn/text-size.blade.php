{{-- Taille du texte A / A+ / A++ : mémorisée dans le navigateur (localStorage), appliquée à <html data-text-size>. --}}
<div
    x-data="{
        size: (() => { try { return window.localStorage.getItem('tn.text-size') || 'md'; } catch (e) { return 'md'; } })(),
        set(value) {
            this.size = value;
            document.documentElement.dataset.textSize = value;
            try { window.localStorage.setItem('tn.text-size', value); } catch (e) {}
        },
    }"
    role="radiogroup"
    aria-label="Taille du texte"
    {{ $attributes->class('inline-flex items-center rounded-sm border border-line bg-surface-2 p-0.5') }}
>
    @foreach ([['md', 'A', 'Texte normal'], ['lg', 'A+', 'Texte agrandi (125 %)'], ['xl', 'A++', 'Texte très agrandi (150 %)']] as [$valeur, $libelle, $aide])
        <button
            type="button"
            role="radio"
            x-on:click="set('{{ $valeur }}')"
            x-bind:aria-checked="size === '{{ $valeur }}' ? 'true' : 'false'"
            x-bind:class="size === '{{ $valeur }}' ? 'bg-cyan text-on-cyan' : 'text-ink-2 hover:text-ink'"
            aria-label="{{ $aide }}"
            title="{{ $aide }}"
            class="inline-flex h-10 min-w-11 items-center justify-center rounded-xs px-2 font-semibold transition-colors"
        >{{ $libelle }}</button>
    @endforeach
</div>
