@props([
    'chemin',
    'alt' => '',
    'prioritaire' => false,
    'sizes' => '100vw',
    // F69 : image privée servie par une route protégée (ex. route('signalements.photo', $s)) au lieu de /storage.
    'url' => null,
])

@php
    $dimensions = \App\Services\OptimiseurImage::dimensions($chemin);
    $miniature = \App\Services\OptimiseurImage::miniature($chemin);
    $src = $url ?? \Illuminate\Support\Facades\Storage::url($chemin);
    $srcMiniature = $miniature ? ($url !== null ? $url.(str_contains($url, '?') ? '&' : '?').'miniature=1' : \Illuminate\Support\Facades\Storage::url($miniature)) : null;
    $srcset = $srcMiniature && $dimensions
        ? $srcMiniature.' '.\App\Services\OptimiseurImage::LARGEUR_MINIATURE.'w, '.$src.' '.$dimensions[0].'w'
        : null;
@endphp

{{-- Image envoyée par un utilisateur (F60) : WebP, dimensions connues, srcset et chargement différé hors écran initial. --}}
<img
    src="{{ $src }}"
    alt="{{ $alt }}"
    @if ($dimensions) width="{{ $dimensions[0] }}" height="{{ $dimensions[1] }}" @endif
    @if ($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif
    @if ($prioritaire) fetchpriority="high" @else loading="lazy" @endif
    decoding="async"
    {{ $attributes }}
/>
