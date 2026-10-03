@props(['items' => [], 'home' => true])

{{--
    Fil d'Ariane : ['Libellé' => url, …, 'Page courante' => null].
    « Accueil » est ajouté automatiquement en premier niveau (désactivable avec :home="false").
--}}
@php
    $niveaux = $home ? ['Accueil' => route('home')] + $items : $items;
@endphp
<nav {{ $attributes }} aria-label="Fil d'Ariane">
    <ol class="flex flex-wrap items-center gap-1.5 font-mono text-[11px] uppercase tracking-[.06em] text-ink-2">
        @foreach ($niveaux as $libelle => $url)
            <li class="flex min-w-0 items-center gap-1.5">
                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" wire:navigate class="inline-flex min-h-6 items-center gap-1 hover:text-cyan focus-visible:text-cyan">
                        @if ($loop->first && $home)
                            <flux:icon name="house" class="size-3.5" aria-hidden="true" />
                        @endif
                        {{ $libelle }}
                    </a>
                    <span aria-hidden="true" class="text-ink-2/60">›</span>
                @else
                    <span aria-current="page" class="max-w-[60vw] truncate text-ink md:max-w-xs" title="{{ $libelle }}">{{ $libelle }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
