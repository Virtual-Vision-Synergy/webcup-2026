@props([
    'chemin',
    'alt' => '',
    'prioritaire' => false,
    'sizes' => '100vw',
])

@php
    $dimensions = \App\Services\OptimiseurImage::dimensions($chemin);
    $miniature = \App\Services\OptimiseurImage::miniature($chemin);
    $srcset = $miniature && $dimensions
        ? \Illuminate\Support\Facades\Storage::url($miniature).' '.\App\Services\OptimiseurImage::LARGEUR_MINIATURE.'w, '.\Illuminate\Support\Facades\Storage::url($chemin).' '.$dimensions[0].'w'
        : null;
@endphp

{{-- Image envoyée par un utilisateur (F60) : WebP, dimensions connues, srcset et chargement différé hors écran initial. --}}
<img
    src="{{ \Illuminate\Support\Facades\Storage::url($chemin) }}"
    alt="{{ $alt }}"
    @if ($dimensions) width="{{ $dimensions[0] }}" height="{{ $dimensions[1] }}" @endif
    @if ($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif
    @if ($prioritaire) fetchpriority="high" @else loading="lazy" @endif
    decoding="async"
    {{ $attributes }}
/>
