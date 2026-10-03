@props(['items' => []])

{{-- Fil d'Ariane : ['Libellé' => url, …, 'Page courante' => null]. --}}
<nav {{ $attributes }} aria-label="Fil d'Ariane">
    <ol class="flex flex-wrap items-center gap-1.5 font-mono text-[11px] uppercase tracking-[.06em] text-ink-2">
        @foreach ($items as $libelle => $url)
            <li class="flex items-center gap-1.5">
                @if ($url)
                    <a href="{{ $url }}" wire:navigate class="inline-flex min-h-6 items-center hover:text-cyan">{{ $libelle }}</a>
                    <span aria-hidden="true" class="text-ink-2/60">/</span>
                @else
                    <span aria-current="page" class="text-ink">{{ $libelle }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
