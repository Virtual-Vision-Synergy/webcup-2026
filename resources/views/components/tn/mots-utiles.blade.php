@props(['slugs' => []])

@php
    // D13 : ligne « Mots utiles » qui regroupe les infobulles du lexique pertinentes pour la page.
    $termes = array_filter(array_map(fn (string $slug): ?array => \App\Support\Lexique::terme($slug), $slugs));
@endphp

@if (count($termes))
    <p {{ $attributes->class('text-sm text-ink-2') }} data-test="mots-utiles">
        <span class="font-semibold text-ink">{{ __('Mots utiles :') }}</span>
        @foreach ($termes as $terme)
            <x-tn.terme :slug="$terme['slug']">{{ $terme['terme'] }}</x-tn.terme>@if (! $loop->last)<span aria-hidden="true"> · </span>@endif
        @endforeach
    </p>
@endif
